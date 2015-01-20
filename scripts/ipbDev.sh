#!/bin/bash

function symlinkForumFile() {
   if [[ -e "$forumPath/$2" ]]; then
      echo "$forumPath/$2 already exists, skipping" 1>&2
      return
   fi
   echo "Symlinking '$baseDir/$1' to '$forumPath/$2'" 1>&2
   ln -s "$baseDir/$1" "$forumPath/$2"
}

# Symlinks source files to the correct areas
declare -r LOCAL_UPLOADS_DIR='upload'
declare -r LOCAL_HOOKS_DIR='hooks'
declare -r LOCAL_SKIN_DIR='skin'
declare -r LOCAL_LANG_DIR='lang'

declare -r REMOTE_HOOKS_DIR='hooks'
declare -r REMOTE_SKIN_DIR='cache/skin_cache/master_skin'
declare -r REMOTE_LANG_DIR='cache/lang_cache/master_lang'

# Grab the forum path as the first argument
fileSource="${BASH_SOURCE[0]}"
while [ -L "$fileSource" ]
do
   symlinkDir=`cd "\`dirname $fileSource\`" && pwd`
   fileSource=`readlink "$fileSource"`
   [[ $fileSource != /* ]] && fileSource="$symlinkDir/$fileSource"
done

binDir=`dirname "$fileSource"` 
binDir=`cd "$binDir"; pwd`
baseDir=`dirname "$binDir"` 

# Check args
if [[ $# -ne 1 ]]; then
   echo "Usage: $0 (forum_root)" 1>&2
   exit 2
fi

# Sanity checks
if [[ ! -d $1 ]]; then
   echo "Unexpected: $1 is not a directory" 1>&2
   exit 2
fi
if [[ ! -f "$1/conf_global.php" ]]; then
   echo "Warning: $1 is probably not IPB" 1>&2
   exit 2
fi

# This is not great and clobbers files, and leaves things lying around
forumPath=$1

symlinkForumFile 'upload/admin/sources/classes/steamcf.php' 'admin/sources/classes/steamcf.php'
symlinkForumFile 'hooks/steamIDCustomFieldCartReplacer.php' 'hooks/steamIDCustomFieldCartReplacer.php'
symlinkForumFile 'hooks/steamIDCustomFieldJSResource.php' 'hooks/steamIDCustomFieldJSResource.php'
symlinkForumFile 'hooks/steamIDCustomFieldNoop.php' 'hooks/steamIDCustomFieldNoop.php'
symlinkForumFile 'hooks/steamIDCustomFieldReplacer.php' 'hooks/steamIDCustomFieldReplacer.php'
symlinkForumFile 'hooks/steamIDCustomFieldVerify.php' 'hooks/steamIDCustomFieldVerify.php'
symlinkForumFile 'skin/skin_steamcf.php' 'cache/skin_cache/master_skin/skin_steamcf.php'
symlinkForumFile 'lang/nexus_public_steamcf.php' 'cache/lang_cache/master_lang/nexus_public_steamcf.php'

exit 0
