#!/bin/bash
cd "$(dirname "$0")/../.."
PHP=/c/xampp/php/php.exe
run() { echo "== $*"; $PHP -d memory_limit=1024M spark "$@" 2>&1 | grep -v "^$\|Server Time" | tail -4; }
run labdocs:import-files "#DTP-Lab-109 Calibration Checklist (DAILY and WEEKLY)" --tag 109
run labdocs:import-books "#Dtp-Lab-110 Standard Reference Material Forms" --code DTP-Lab-110-Forms --force
run labdocs:import-files "#Dtp-Lab-110 Standard Reference Material Monitoring" --code DTP-Lab-110-Monitoring --tag 110m
run labdocs:import-books "#DTP-Lab-114 Conditioned Environment Failure" --code DTP-Lab-114-Failure --force
run labdocs:import-files "#DTP-Lab-114 Temperature and Humidity" --code DTP-Lab-114-Temp --tag 114t
echo ALLDONE
