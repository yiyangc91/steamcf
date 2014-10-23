#!/bin/bash
# Symlinks source files to the correct areas

declare -r LOCAL_UPLOADS_DIR='upload'
declare -r LOCAL_HOOKS_DIR='hooks'
declare -r LOCAL_SKIN_DIR='skin'
declare -r LOCAL_LANG_DIR='lang'

declare -r REMOTE_HOOKS_DIR='hooks'
declare -r REMOTE_SKIN_DIR='cache/skin_cache/master_skin'
declare -r REMOTE_LANG_DIR='cache/lang_cache/master_lang'

# Get script root directory
script_path=$(readlink -f "${BASH_SOURCE[0]}")
script_root=$(dirname $(dirname "$script_path"))

# Grab the forum path as the first argument
if [[ $# -ne 1 ]]; then
   echo "Usage: $0 <forum_path>" 1>&2
   exit 1
fi

# This is not great and clobbers files, and leaves things lying around
echo "Symlinking files in repository to forum locations..."
forum_path=$1
cp -srv "$script_root/$LOCAL_UPLOADS_DIR/." "$forum_path"
cp -srv "$script_root/$LOCAL_HOOKS_DIR/." "$forum_path/$REMOTE_HOOKS_DIR"
cp -srv "$script_root/$LOCAL_SKIN_DIR/." "$forum_path/$REMOTE_SKIN_DIR"
cp -srv "$script_root/$LOCAL_LANG_DIR/." "$forum_path/$REMOTE_LANG_DIR"

# Settings cannot be merely copied, and need to be configured in IPB... have fun.
echo "You'll need to manually sync settings"

exit 0
