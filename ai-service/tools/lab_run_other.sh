#!/bin/bash
# "Other Documents": every folder of the Lab Documents share that is not a numbered "#" folder.
# Previews + import; the files are kept as copies on the server's disk (writable/labdocs/other), not in the database, because they are several GB.
#   bash lab_run_other.sh            (all of them)     bash lab_run_other.sh "Misc"   (one)
cd "$(dirname "$0")/../.."
PHP=/c/xampp/php/php.exe
export PYTHONIOENCODING=utf-8
ROOT="//192.168.32.130/qc lab files/#Lab Documents"
n=0
for dir in "$ROOT"/*/; do
  f="$(basename "$dir")"
  case "$f" in "#"*) continue;; esac
  if [ -n "$1" ] && [ "$f" != "$1" ]; then continue; fi
  n=$((n+1))
  code="OTHER-$(echo "$f" | sed 's/[^A-Za-z0-9]\+/-/g; s/^-//; s/-$//')"
  tag="o$n"
  echo "== $f ($code)"
  bash ai-service/tools/lab_prepare.sh "$f" "$tag" 2>&1 | tail -2
  $PHP -d memory_limit=1024M spark labdocs:import-files "$f" --code "$code" --tag "$tag" --group other --disk 2>&1 | grep -v "^$\|Server Time" | tail -2
done
echo ALLDONE
