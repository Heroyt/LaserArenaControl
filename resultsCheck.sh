#!/bin/bash

while sleep 0.1; do
    ls lmx/results/*.game | entr -n -d php index.php results/load lmx/results/
done