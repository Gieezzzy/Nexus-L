#!/bin/bash
# Prepares the previews of a Laboratory Documents folder for labdocs:import-files:
#   lab_prepare.sh "<folder name on the share>" <tag>
# thumbs<tag> = first page / small copy of PDFs and pictures, conv<tag> = Word documents as PDF, thumbsconv<tag> = first page of those.
set -e
ROOT="//192.168.32.130/qc lab files/#Lab Documents/$1"
TAG="$2"
PY="C:/NexusAI/venv/Scripts/python.exe"
T="$(dirname "$0")"
$PY -W ignore "$T/lab_thumbs.py" "$ROOT" "E:/NexusAI/vfm/thumbs$TAG"
if find "$ROOT" -type f \( -iname '*.docx' -o -iname '*.doc' -o -iname '*.pptx' -o -iname '*.ppt' -o -iname '*.xls' \) -not -name '~$*' | grep -q .; then
  $PY -W ignore "$T/lab_docpdf.py" "$ROOT" "E:/NexusAI/vfm/conv$TAG"
  $PY -W ignore "$T/lab_thumbs.py" "E:/NexusAI/vfm/conv$TAG" "E:/NexusAI/vfm/thumbsconv$TAG"
fi
