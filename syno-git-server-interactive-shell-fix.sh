echo "Running pre-checks..."

if [[ "$(whoami)" != "root" ]] ; then
  echo -e "\033[0;31m- Missing sudo privileges. Run 'sudo -i' and try again."
  exit 1
fi
echo "- Sudo privileges"

syno_running=$(/usr/syno/bin/synopkg status Git | grep -c running)
if [[ $syno_running -ne 0 ]] ; then
  echo -e "\033[0;31m- Synology Git Server package is running. Stop it and try again."
  exit 1
fi
echo "- Synology Git Server package status"

bin_synology="/var/packages/Git/target/bin/git-shell"
if [ ! -f "$bin_synology" ]; then
  echo -e "\033[0;31m- Synology Git Server binary not found. Install the package and try again."
  exit 1
fi
echo "- Synology Git Server binary"

bin_community="/var/packages/git/target/bin/git-shell"
if [ ! -f "$bin_community" ]; then
  echo -e "\033[0;31m- Synology Community Git binary not found. Install the package and try again."
  exit 1
fi
echo "- Synology Community Git binary"

backup_dir="syno-git-server-interactive-shell-fix-backup"
if [ -d "$backup_dir" ]; then
  echo -e "\033[0;31m- Backup directory exists already. Delete/move it and try again."
  exit 1
fi
echo "- Backup directory"


echo "Backup binaries..."
mkdir $backup_dir
cd $backup_dir

mkdir synology
cp $bin_synology synology/
echo "- Synology (original)"

mkdir community
cp $bin_community community/
echo "- Community (replacement)"


echo "Replacing official binary by commity version..."
cp community/git-shell $bin_synology
echo "Finished! Start Synology Git Server package and try to connect."

