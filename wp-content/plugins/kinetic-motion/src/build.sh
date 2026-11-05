#!/bin/sh
# Rebuild the minified scripts in assets/js from the readable sources in src/.
# Needs esbuild. For the WebGL bundle also a copy of three.js:
#   THREE=/path/to/three/build/three.module.js sh src/build.sh
set -e
cd "$(dirname "$0")/.."
THREE="${THREE:-node_modules/three/build/three.module.js}"
esbuild src/motion.js --minify --target=es2018 --legal-comments=inline --outfile=assets/js/kmo-motion.js
esbuild src/site.js --minify --target=es2018 --legal-comments=inline --outfile=assets/js/kmo-site.js
esbuild src/webgl.js --bundle --minify --format=iife --target=es2019 --legal-comments=none \
  --alias:three="$THREE" --outfile=assets/js/kmo-webgl.js
printf '/*! Kinetic Motion WebGL effects — includes three.js (MIT, https://threejs.org) */\n' | cat - assets/js/kmo-webgl.js > assets/js/kmo-webgl.tmp && mv assets/js/kmo-webgl.tmp assets/js/kmo-webgl.js
