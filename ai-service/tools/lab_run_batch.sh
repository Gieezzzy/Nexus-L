#!/bin/bash
# Previews + import for numbered Lab Documents folders:  bash lab_run_batch.sh "<folder name>|<tag>" ["<folder name>|<tag>" ...]
# (the code of the register is read from the folder name; the tag names the preview folders under E:\NexusAI\vfm)
cd "$(dirname "$0")/../.."
PHP=/c/xampp/php/php.exe
export PYTHONIOENCODING=utf-8
for a in "$@"; do
  f="${a%%|*}"; t="${a##*|}"
  echo "== $f"
  bash ai-service/tools/lab_prepare.sh "$f" "$t" 2>&1 | tail -3
  $PHP -d memory_limit=1024M spark labdocs:import-files "$f" --tag "$t" 2>&1 | grep -v "^$\|Server Time" | tail -3
done
echo ALLDONE
