#!/usr/bin/env python3
"""
Empaquetador del plugin para distribución.

Sustituye a Compress-Archive de PowerShell y a ZipFile de .NET: ninguno de los
dos escribe permisos Unix en las entradas del ZIP, así que al extraer en un
servidor Linux los archivos quedan con los permisos que decida el sistema. Si
el usuario del servidor web no puede escribir en ellos, WordPress falla en la
siguiente actualización con "No se ha podido eliminar el plugin actual".

Aquí se fijan explícitamente 0644 en archivos y 0755 en directorios, y se
garantiza que todos los separadores son '/' (la especificación ZIP no admite
otra cosa; con '\\' Linux crea archivos planos con la ruta en el nombre en
lugar de un árbol de directorios).

La lista de exclusiones se lee de .distignore, para no tener dos fuentes de
verdad sobre qué viaja en el distribuible.

Uso:  python tools/build-zip.py
"""

from __future__ import annotations

import re
import sys
import zipfile
from pathlib import Path

PLUGIN_SLUG = "wp-multi-post-type-blog"
LOADER = "wp-multi-post-type-blog-block.php"
ROOT = Path(__file__).resolve().parent.parent
OUTPUT_DIR = ROOT / "000 Versiones"

# Nombres que nunca deben viajar, estén donde estén.
EXCLUDE_NAMES = {"Thumbs.db", ".DS_Store", "desktop.ini", ".gitkeep"}

DIR_ATTR = (0o040755 << 16)
FILE_ATTR = (0o100644 << 16)


def read_versions() -> tuple[str, str]:
    loader = (ROOT / LOADER).read_text(encoding="utf-8")

    header = re.search(r"^\s*\*\s*Version:\s*([0-9][0-9.]*)", loader, re.M)
    const = re.search(r"define\(\s*'WP_MULTIPOST_BLOG_VERSION',\s*'([0-9.]+)'", loader)

    if not (header and const):
        sys.exit("No se pudieron leer las dos versiones del plugin.")
    return header.group(1), const.group(1)


def read_exclusions() -> tuple[set[str], set[str]]:
    """Separa .distignore en prefijos de directorio y rutas exactas."""
    dirs: set[str] = set()
    paths: set[str] = set()

    distignore = ROOT / ".distignore"
    if not distignore.is_file():
        return dirs, paths

    for line in distignore.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if line.endswith("/"):
            dirs.add(line.rstrip("/"))
        else:
            paths.add(line)

    return dirs, paths


def main() -> None:
    version, constant = read_versions()
    # Comprobación barata que evita publicar un paquete descoordinado.
    if version != constant:
        sys.exit(
            f"Versiones desincronizadas: header={version} "
            f"WP_MULTIPOST_BLOG_VERSION={constant}"
        )

    excluded_dirs, excluded_paths = read_exclusions()

    OUTPUT_DIR.mkdir(exist_ok=True)
    zip_path = OUTPUT_DIR / f"{PLUGIN_SLUG} {version}.zip"
    if zip_path.exists():
        zip_path.unlink()

    files = dirs = 0
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for item in sorted(ROOT.rglob("*")):
            # as_posix() garantiza '/' aunque se ejecute en Windows.
            relative = item.relative_to(ROOT).as_posix()
            parts = relative.split("/")

            if relative in excluded_paths or any(p in excluded_dirs for p in parts):
                continue
            if item.name in EXCLUDE_NAMES:
                continue

            entry = f"{PLUGIN_SLUG}/{relative}"

            if item.is_dir():
                info = zipfile.ZipInfo(entry + "/")
                info.external_attr = DIR_ATTR
                zf.writestr(info, b"")
                dirs += 1
            else:
                info = zipfile.ZipInfo.from_file(item, entry)
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = FILE_ATTR
                zf.writestr(info, item.read_bytes())
                files += 1

    size_kb = zip_path.stat().st_size / 1024
    print(f"ZIP creado: {zip_path.name}")
    print(f"  version {version} · {files} archivos · {dirs} directorios · {size_kb:,.0f} KB")


if __name__ == "__main__":
    main()
