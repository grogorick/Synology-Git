<?php

/*
Required Apps:
	- PHP 7.0
	- Web Station
	- Git Server
*/



require("config.php");

define("TEXT_LOADING", "<i>( lädt ... )</i>");



function shell($cmd) {
	$ret = shell_exec($cmd);
	if (substr($ret, -1) === "\n") {
		$ret = substr($ret, 0, -1);
	}
	return $ret;
}

define("ls", "ls -1;");
define("pwd", "pwd;");
define("cd_git", "cd " . CONFIG_GIT_BASE_PATH . ";");
define("count_files", "ls -1 | wc -l;");
define("ssh_git", "ssh " . CONFIG_SSH_USER . "@localhost -p " . CONFIG_SSH_PORT . " ");
define("image_file_formats", [
		"png"  => "png",
		"jpg"  => "jpg",
		"jpeg" => "jpg",
		"gif"  => "gif",
		"svg"  => "svg+xml"
	]);

function lines($str) {
	return array_filter(explode("\n", $str));
}

function readable_filesize($filesize) {
	for ($i = 0; ($filesize / 1024) > 0.9; $i++, $filesize /= 1024) {}
	return round($filesize, 2 /* precision */) . " " . ['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
}



if (isset($_GET["phpinfo"])) {
	phpinfo();
	exit;
}

if (isset($_GET["request"])) {
	session_start();
	if (isset($_SESSION['auth']) && time() - $_SESSION['auth'] <= CONFIG_SESSION_TIMEOUT) {
		switch ($_GET["request"]) {

			case "list-public-keys": {
				$keys = lines(shell(ssh_git . "list_keys"));
				$second_row = true;
				foreach ($keys as $idx => $key) {
					$key = explode(" ", $key);
					$row_code_second = ($second_row = !$second_row) ? " class=\"second_row\"" : "";
?>
					<tr<?=$row_code_second?> 
						onclick="document.getElementById('key_full_<?=$idx?>').classList.toggle('hidden');">
						<td class="clickable"><?=/* User@Machine */$key[3]?></td>
						<td class="clickable"><?=/* Fingerprint */$key[2]?></td>
						<td class="clickable"><?=/* Encryption */$key[4]?></td>
						<td style="text-align: right;">
							<form action="" method="post">
								<input type="hidden" name="action" value="delete_key" />
								<input type="hidden" name="key_idx" value="<?=($idx + 1)?>" />
								<input type="hidden" name="key_user" value="<?=$key[3]?>" />
								<input type="submit" value="&cross;" title="Public Key löschen" class="button" 
									onclick="return confirm('Den Public Key von `<?=$key[3]?>` wirklich löschen?');" />
							</form>
						</td>
					</tr>
					<tr id="key_full_<?=$idx?>" class="hidden">
						<td></td>
						<td colspan="3">
							<div class="max-width-column break-lines">
								<?=shell(ssh_git . "show_key " . ($idx + 1));?>
							</div>
						</td>
					</tr>
<?php
				}
			} break;

			case "repo-details": {
				$git_name = urldecode($_GET["git_name"]);
				$git_dir = escapeshellarg($git_name . ".git");
				$git_url = "ssh://" . CONFIG_SSH_USER . "@" . CONFIG_SERVER . ":" . CONFIG_SSH_PORT . CONFIG_GIT_BASE_PATH . $git_name . ".git";

				$branches = lines(shell(cd_git . "cd $git_dir;" . "git branch"));
				$tags = lines(shell(cd_git . "cd $git_dir;" . "git tag"));
				$num_commits = shell(cd_git . "cd $git_dir;" . "git rev-list --all --count");
				$allow_force_push = preg_match("/=false$/i", shell(cd_git . "cd $git_dir;" . "git config --local -l | grep -i receive.denynonfastforwards"));
?>
				<p>
					<span class="code select_all">git clone <?=$git_url?></span>
				</p>
				<p>
					<?=sipl(count($branches), "Branch", "Branches")?>
					<span class="indented">
<?php
				foreach ($branches as $i => $branch_disp) {
					$branch = trim(substr($branch_disp, 2)); // remove '* ' before HEAD branch and '  ' before branches
					$responseSpanId = "file_browser_" . $git_name . "_" . $branch;
?>
						<?=$i ? "<br />" : ""?>
						<span class="clickable" onclick="toggleFileBrowser(arguments[0], '<?=$git_name?>', '<?=$branch?>:', '<?=$responseSpanId?>')"><?=$branch_disp?></span>
						<span class="file-browser hidden" id="<?=$responseSpanId?>"></span>
<?php
				}
?>
					</span>
				</p>
				<p>
					<?=sipl(count($tags), "Tag", "Tags")?>
					<span class="indented">
						<?=implode("<br />", $tags)?> 
					</span>
				</p>
				<p>
					<span class="clickable" onclick="toggleCommits('<?=$git_name?>', 'commits_<?=$git_name?>')"><?=sipl($num_commits, "Commit", "Commits")?></span>
					<table class="commits hidden" id="commits_<?=$git_name?>"></table>
				</p>

				<h1 style="cursor: pointer;"
					onclick="document.getElementById('advanced_settings_<?=$git_name?>').classList.toggle('hidden');">Erweiterte Einstellungen</h1>
				<div id="advanced_settings_<?=$git_name?>" class="hidden" style="padding-bottom: 20px;">
					Erlaube <i>force push</i>: &nbsp; 
<?php
				if ($allow_force_push) {
?>
					Ja &nbsp; 
					<form action="" method="post" style="display: inline-block;">
						<input type="hidden" name="action" value="allow_force_push" />
						<input type="hidden" name="action_value" value="no" />
						<input type="hidden" name="git_name" value="<?=$git_name?>" />
						<input type="submit" value="Verbieten" title="Git Repository löschen" class="button" />
					</form>
<?php
				} else {
?>
					Nein &nbsp; 
					<form action="" method="post" style="display: inline-block;">
						<input type="hidden" name="action" value="allow_force_push" />
						<input type="hidden" name="action_value" value="yes" />
						<input type="hidden" name="git_name" value="<?=$git_name?>" />
						<input type="submit" value="Erlauben" title="Git Repository löschen" class="button" />
					</form>
<?php
				}
?>
				</div>
<?php
			} break;

			case "list-commits": {
				$git_name = urldecode($_GET["git_name"]);
				$num_latest_commits = isset($_GET["num"]) ? intval($_GET["num"]) : CONFIG_NUM_LATEST_COMMITS;
				$git_dir = escapeshellarg($git_name . ".git");
				$num_commits = shell(cd_git . "cd $git_dir;" . "git rev-list --all --count");
				$log = lines(shell(cd_git . "cd $git_dir;" . "git log -" . $num_latest_commits . " --graph --all --date-order --format=format:'___%h___%ar___%s___%an___%d'"));

				foreach ($log as $commit) {
					$commit = explode("___", $commit);
					if (count($commit) > 1) {
?>
						<tr>
							<td><?=str_replace(" ", "&nbsp;", $commit[0 /* graph */])?></td>
							<td><?=$commit[3 /* message */]?></td>
							<td><?=str_replace(" ", "&nbsp;", $commit[4 /* name */])?></td>
							<td>(<?=str_replace(" ", "&nbsp;", $commit[2 /* date */])?>)</td>
							<td><?=$commit[5 /* branch */]?></td>
							<td><?=$commit[1 /* hash */]?></td>
						</tr>
<?php
					}
					else { // graph fork/join
?>
						<tr><td><?=$commit[0 /* graph */]?></td></tr>
<?php
					}
				}
				if ($num_commits > $num_latest_commits) {
?>
						<tr id="more_commits_<?=$git_name?>"><td class="clickable" onclick="moreCommits('<?=$git_name?>', <?=($num_latest_commits + CONFIG_NUM_LATEST_COMMITS)?>, 'more_commits_<?=$git_name?>')">...</td></tr>
<?php
				}
			} break;

			case "file-browser": {
				$git_name = urldecode($_GET["git_name"]);
				$git_dir = escapeshellarg($git_name . ".git");
				$ref = urldecode($_GET["ref"]);
				$tmpPos = strpos($ref, ":") ?: strlen($ref);
				$branch = substr($ref, 0, $tmpPos);
				$path = substr($ref, $tmpPos + 1);
				$files = lines(shell(cd_git . "cd $git_dir;" . "git ls-tree -l --abbrev " . escapeshellarg($ref)));
				foreach ($files as $i => $file) {
					$f = preg_split("/\\s+/", $file);

					// file
					if ($f[1] === "blob") {
						$responseSpanId = "file_browser_" . $git_name . "_" . $ref . $f[4];
						$filesize = readable_filesize(intval($f[3]));
?>
							<span class="clickable" onclick="toggleFileContent(arguments[0], '<?=$git_name?>', '<?=$ref . $f[4]?>', '<?=$responseSpanId?>')"><?=$f[4]?><i> ( <?=$filesize?> )</i></span>
							<span class="file-browser hidden" id="<?=$responseSpanId?>"></span>
<?php
					}

					// directory
					else if ($f[1] === "tree") {
						$responseSpanId = "file_browser_" . $git_name . "_" . $ref . $f[4] . "/";
?>
							<span class="clickable" onclick="toggleFileBrowser(arguments[0], '<?=$git_name?>', '<?=$ref . $f[4] . "/"?>', '<?=$responseSpanId?>')"><?=$f[4]?>/</span>
							<span class="file-browser hidden" id="<?=$responseSpanId?>"></span>
<?php
					}

					// submodule
					else if ($f[1] === "commit") {
						$submodule = shell(cd_git . "cd $git_dir;" . "git config --blob $branch:.gitmodules --get-regex ^submodule.*.path$ $path");
						$submodule = preg_replace("/^submodule[.](.+)[.]path .+$/", "$1", $submodule);
						$submoduleURL = shell(cd_git . "cd $git_dir;" . "git config --blob master:.gitmodules --get submodule.$submodule.url");
?>
							<?=$path . $f[4]?>/ <font class="inline-code">(<?=$submoduleURL?> @ <?=$f[2]?>)</font>
<?php
					}

					else { /* this should not happen */
?>
							<span style="color: red;"><?=$file?></span>
<?php
					}
				}
			} break;

			case "file-content": {
				$git_name = urldecode($_GET["git_name"]);
				$git_dir = escapeshellarg($git_name . ".git");
				$ref = escapeshellarg(urldecode($_GET["ref"]));
				$file_name = substr($ref, strpos($ref, ":") + 1, -1);
				$file_ext = strrpos($ref, "."); $file_ext = $file_ext === false ? "" : strtolower(substr($ref, $file_ext + 1, -1));
				$file_content = shell(cd_git . "cd $git_dir;" . "git show $ref");
				if (in_array($file_ext, array_keys(image_file_formats))) {
					header("Content-Type: image/" . image_file_formats[$file_ext]);
					header("Content-Disposition: attachment; filename=\"" . $file_name . "\"");
					echo $file_content;
					break;
				}
				else {
					$file_content = htmlspecialchars($file_content);
				}
				if ($file_content == "") {
					$file_content = "<i class='indented'>(Dieses Dateiformat kann nicht angezeigt werden)</i>";
				} else {
					$file_content = explode("\n", $file_content);
					$file_content = array_map(function($line, $n) { return "<tr><td>" . ($n + 1) . "</td><td>" . $line . "</td></tr>"; }, $file_content, array_keys($file_content));
					$file_content = "<table class='code file_content'>" . implode("", $file_content) . "</table>";
				}
?>
								<?=$file_content?>
<?php
			} break;
		}
		exit;
	}
	else {
		session_destroy();
		http_response_code(404);
		exit;
	}
}



function show_header() {
?>
<html>
	<head>
		<title>Schatzkiste</title>
		<link rel="stylesheet" href="style/general.css">
		<style>
			.commits td {
				padding-left: 20px;
				padding-top: 5px;
			}
			.commits td:nth-child(1), .commits td:nth-child(n+3) {
				color: gray;
			}
			.file-browser span {
				display: block;
			}
			span.file-browser {
				display: block;
				padding-left: 10px;
			}
			span.file-browser > i {
				padding-left: 20px;
				color: gray;
			}
			table.file_content {
				border-left: 0;
				padding-left: 0;
			}
			table.file_content td:first-child {
				padding-right: 10px;
				border-right: 1px solid gray;
				color: gray;
				user-select: none;
			}
			table.file_content td:nth-child(2) {
				padding-left: 10px;
				white-space: pre-wrap;
			}
			img.file_content {
				max-width: 100%;
			}
		</style>
		<script>
			function request(url, responseTarget) {
				var xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function() {
					if (this.readyState == 4 && this.status == 200) {
						if (typeof responseTarget === "function") {
							responseTarget(this.responseText);
						} else {
							responseTarget.innerHTML = this.responseText;
						}
					}
				};
				xhttp.open("GET", url, true);
				xhttp.send();
			}

			function togglePublicKeys() {
				var keys_table = document.getElementById("public_keys_table");
				if (keys_table.innerHTML.trim() == "") {
					request("/?request=list-public-keys", keys_table);
					keys_table.innerHTML = "<?=TEXT_LOADING?>";
				}
				document.getElementById('public_key_management').classList.toggle('hidden');
			}

			function toggleRepoDetails(git_name) {
				var git_details_div = document.getElementById("git_details_" + git_name);
				if (git_details_div.innerHTML.trim() == "") {
					request("/?request=repo-details&git_name=" + encodeURI(git_name), function(response_details) {
						git_details_div.innerHTML = response_details;
						prepareSelectAllOnlyOnce(git_details_div);
					});
					git_details_div.innerHTML = "<?=TEXT_LOADING?>";
				}
				document.getElementById("git_details_row_" + git_name).classList.toggle('hidden');
			}

			function toggleCommits(git_name, responseTargetId) {
				var responseTarget = document.getElementById(responseTargetId);
				if (responseTarget.innerHTML.trim() == "") {
					request("?request=list-commits&git_name=" + encodeURI(git_name), responseTarget);
					responseTarget.innerHTML = "<?=TEXT_LOADING?>";
				}
				responseTarget.classList.toggle('hidden');
			}

			function moreCommits(git_name, num_commits, moreCommitsTagId) {
				var moreCommitsTag = document.getElementById(moreCommitsTagId);
				var responseTarget = moreCommitsTag.parentNode;
				request("?request=list-commits&git_name=" + encodeURI(git_name) + "&num=" + num_commits, function(responseText) {
					responseTarget.innerHTML = responseText;
				});
				moreCommitsTag.innerHTML = "<td colspan=2><?=TEXT_LOADING?></td>";
			}

			function toggleFileBrowser(event, git_name, ref, responseTargetId) {
				event.stopPropagation();
				var responseTarget = document.getElementById(responseTargetId);
				if (responseTarget.innerHTML.trim() == "") {
					request("/?request=file-browser&git_name=" + encodeURI(git_name) + "&ref=" + encodeURI(ref), responseTarget);
					responseTarget.innerHTML = "<?=TEXT_LOADING?>";
				}
				responseTarget.classList.toggle('hidden');
			}

			function toggleFileContent(event, git_name, ref, responseTargetId) {
				event.stopPropagation();
				console.log(responseTargetId);
				var responseTarget = document.getElementById(responseTargetId);
				if (responseTarget.innerHTML.trim() == "") {
					var fileContentURI = "/?request=file-content&git_name=" + encodeURI(git_name) + "&ref=" + encodeURI(ref);
					var fileExt = ref.lastIndexOf("."); fileExt = (fileExt === -1) ? "" : ref.substr(fileExt + 1);
					if (["<?=implode("\", \"", array_keys(image_file_formats))?>"].includes(fileExt)) {
						var img = document.createElement("img");
						img.src = fileContentURI;
						img.classList.add("file_content");
						responseTarget.innerHTML = "";
						responseTarget.appendChild(img);
					}
					else {
						request(fileContentURI, responseTarget);
						responseTarget.innerHTML = "<?=TEXT_LOADING?>";
					}
				}
				responseTarget.classList.toggle('hidden');
			}

			function prepareSelectAllOnlyOnce(element) {
				element.querySelectorAll('.select_all').forEach(function(el) {
					['mouseup', 'touchend'].forEach(function(eventType) {
						el.addEventListener(eventType, function() {
							el.classList.remove('select_all');
						});
					});
				});
			}
		</script>
	</head>
	<body>
<?php
}

function show_footer() {
?>
		<script>
			prepareSelectAllOnlyOnce(document);
		</script>
	</body>
</html>
<?php
}



session_start();
if (isset($_SESSION['auth'])) {
	if (time() - $_SESSION['auth'] > CONFIG_SESSION_TIMEOUT) {
		session_unset();
	}
}
if (!isset($_SESSION["auth"])) {
	if (isset($_POST["user"]) && isset($_POST["pass"])) {
		$url = "https://" . CONFIG_DSM_SERVER . "/webapi/auth.cgi?api=SYNO.API.Auth&version=3&session=FileStation&method=login&account=" . $_POST["user"] . "&passwd=" . rawurlencode($_POST["pass"]) . "&format=cookie";
		$response = file_get_contents($url);
		$json = json_decode($response);
		if ($json->success) {
			file_get_contents("https://" . CONFIG_DSM_SERVER . "/webapi/auth.cgi?api=SYNO.API.Auth&version=1&session=FileStation&method=logout");
			session_regenerate_id(true);
			$_SESSION["user"] = $_POST["user"];
			$_SESSION['auth'] = time();
			header("Location: /");
			exit;
		}
	}
	else if (isset($_GET["login"])) {
		show_header();
?>
		<section>
			<form action="" method="post">
				<input type="text" name="user" placeholder="Nutzer" autofocus />
				<input type="password" name="pass" placeholder="Passwort" />
				<input type="submit" value="&rarr;" class="button" />
			</form>
		</section>
<?php
		show_footer();
		exit;
	}
}
if (isset($_GET["logout"])) {
	session_unset();
	header("Location: /?login");
	exit;
}
if (!isset($_SESSION["auth"])) {
	session_destroy();
	http_response_code(404);
	exit;
}
$_SESSION['auth'] = time();



function msg($text) {
	return "<section><span class=\"message\">$text</span></section>\n";
}

function check_new_git_name($git_name) {
	if (strlen($git_name) < 3) {
		return "Der gewählte Name ist zu kurz: mindestens 3 Zeichen.";
	}
	else if (!preg_match("/^[A-Za-z0-9_+\-]+$/", $git_name)) {
		return "Der gewählte Name enthält unerlaubte Zeichen. Benutze nur: A-Z a-z 0-9 _ + -";
	}
	else if (is_dir(CONFIG_GIT_BASE_PATH . $git_name . ".git")) {
		return "Es existiert bereits ein Repository mit dem Namen <b>" . $git_name . "</b>.";
	}
	return true;
}

function sipl($number, $singular, $plural) {
	return $number . " " . ($number == 1 ? $singular : $plural);
}



show_header();
?>



		<section>
			<?=$_SESSION["user"]?> &nbsp; 
			<a href="/?logout" class="button">ausloggen</a>

			<span class="button" style="float: right;" 
				onclick="togglePublicKeys()">
				Public Keys
			</span>
			<hr />
		</section>



		<div id="public_key_management" class="<?=(isset($_POST["action"]) && ($_POST["action"] === "add_key" || $_POST["action"] === "delete_key")) ? "" : "hidden"?>">
			<section>
				Neuen Public Key hinzufügen: &nbsp; 
				<form action="" method="post">
						<input type="hidden" name="action" value="add_key" />
					<input type="text" name="key_add" placeholder="Public Key" style="width: 800px;" /> &nbsp;
					<input type="submit" value="hinzufügen" class="button" />
				</form>
			</section>



<?php
if (isset($_POST["action"])) {
	if ($_POST["action"] === "add_key") {
		$key_add = escapeshellarg(trim(strip_tags($_POST["key_add"])));
		$key_user = substr($key_add, strrpos($key_add, " ") + 1);
		$key_add = escapeshellarg($key_add);
		echo 
"		" . msg("Neuen Public Key hinzufügen... <b>$key_user</b><br />" . 
			"<i>" . shell(ssh_git . "add_key $key_add") . "</i>");
	}
	else if ($_POST["action"] === "delete_key") {
		$key_idx = intval($_POST["key_idx"]);
		$key_user = $_POST["key_user"];
		echo 
"		" . msg("Public Key wird gelöscht... <b>$key_user</b><br />" . 
				"<i>" . shell(ssh_git . "delete_key $key_idx") . "</i>");
	}
}
?>



			<section>
				<h1>Public Keys:</h1>
				<table class="table_padding" id="public_keys_table">
				</table>
			</section>

			<section>
				Mithilfe von Public Keys können Befehle wie <i>git pull</i> oder <i>git push</i> ohne Eingabe von Nutzernamen und Passwort durchgeführt werden. 
				(<span class="inline-code select_all">ssh <?=CONFIG_SSH_USER . "@" . CONFIG_SERVER . " -p " . CONFIG_SSH_PORT?></span>)
				<hr />
			</section>
		</div>



		<section>
			Neues Git Repository erstellen: &nbsp; 
			<form action="" method="post">
				<input type="hidden" name="action" value="create_git" />
				<input type="text" name="git_name" placeholder="Git Name" style="width: 150px;" /> &nbsp; 
				<input type="text" name="git_description" placeholder="Beschreibung" style="width: 500px;" /> &nbsp; 
				<input type="submit" value="erstellen" class="button" />
			</form>
		</section>



<?php
if (isset($_POST["action"])) {
	if ($_POST["action"] === "create_git") {
		$git_name_create = trim(strip_tags($_POST["git_name"]));
		$check_name = check_new_git_name($git_name_create);
		if ($check_name !== true) {
			unset($git_name_create);
			echo 
"		" . msg($check_name);
		}
		else {
			$git_dir = escapeshellarg($git_name_create . ".git");
			$create_git_description = escapeshellarg(trim(strip_tags($_POST["git_description"])));
			echo 
"		" . msg("Neues Git Repository wird erstellt... <b>$git_name_create</b><br />" . 
				"<i>" . shell(cd_git . "mkdir $git_dir;" . "cd $git_dir;" . "git init --bare --shared;" . "echo $create_git_description > description") . "</i>");
		}
	}
	else if ($_POST["action"] === "rename_git") {
		$git_name_rename = trim(strip_tags($_POST["git_name_new"]));
		$check_name = check_new_git_name($git_name_rename);
		if ($check_name !== true) {
			unset($git_name_rename);
			echo 
"		" . msg($check_name);
		}
		else {
			$git_name = $_POST["git_name"];
			$git_dir = escapeshellarg($git_name . ".git");
			$git_dir_rename = escapeshellarg($git_name_rename . ".git");
			echo 
"		" . msg("Git Repository <b>$git_name</b> wird in <b>$git_name_rename</b> umbenannt...<br />" . 
				"<i>" . shell(cd_git . "mv $git_dir $git_dir_rename") . "</i>");
		}
	}
	else if ($_POST["action"] === "edit_git_descrption") {
		$git_name_description = $_POST["git_name"];
		$git_description = escapeshellarg(trim(strip_tags($_POST["git_description_new"])));
		$git_dir = escapeshellarg($git_name_description . ".git");
		echo 
"		" . msg("Beschreibung des Git Repositories <b>$git_name_description</b> wird geändert...<br />" . 
			"<i>" . shell(cd_git . "cd $git_dir;" . "echo $git_description > description;") . "</i>");
	}
	else if ($_POST["action"] === "delete_git") {
		$git_name = $_POST["git_name"];
		$git_dir = escapeshellarg($git_name . ".git");
		echo 
"		" . msg("Git Repository wird gelöscht... <b>$git_name</b><br />" . 
			"<i>" . shell(cd_git . "rm -r $git_dir;") . "</i>");
	}
	else if ($_POST["action"] === "allow_force_push") {
		$git_name_force_push = $_POST["git_name"];
		$git_dir = escapeshellarg($git_name_force_push . ".git");
		if ("yes" === $_POST["action_value"]) {
			echo 
"		" . msg("Force push wird erlaubt... <b>$git_name_force_push</b><br />" . 
				"<i>" . shell(cd_git . "cd $git_dir;" . "git config --local receive.denynonfastforwards false") . "</i>");
		} else {
			echo 
"		" . msg("Force push wird verboten... <b>$git_name_force_push</b><br />" . 
				"<i>" . shell(cd_git . "cd $git_dir;" . "git config --local --unset receive.denynonfastforwards") . "</i>");
		}
	}
}
?>



		<section>
			<h1>Git Repositories:</h1>
<?php
$list = shell(cd_git . ls);
function git_dir_filter($name) { return substr($name, -4) === ".git"; }
function git_dir_trim($name) { return substr($name, 0, -4); }
$git_repos = array_map('git_dir_trim', array_filter(explode("\n", $list), 'git_dir_filter'));

if (empty($git_repos)) {
	echo 
"			" . msg("Noch keine Git Repositories angelegt.");
}
else {
?>
			<table class="table_padding" style="margin-top: 10px;">
<?php
	$second_row = true;
	foreach ($git_repos as $git_name) {
		$git_dir = $git_name . ".git";
		$git_url = "ssh://" . CONFIG_SSH_USER . "@" . CONFIG_SERVER . ":" . CONFIG_SSH_PORT . CONFIG_GIT_BASE_PATH . $git_name . ".git";
		$git_description = shell(cd_git . "cat $git_dir/description");
		$is_empty = shell(cd_git . "cd $git_dir/refs/heads;" . count_files) == "0";

		$row_code_second = ($second_row = !$second_row) ? " second_row" : "";
		$row_code_create = ($git_name_create === $git_name) ? " new" : "";
		$row_code_rename = ($git_name_rename === $git_name) ? " modified" : "";
		$row_code_description = ($git_name_description === $git_name) ? " modified" : "";
		$row_code_force_push = ($git_name_force_push === $git_name) ? " modified" : "";
		$row_code = $row_code_second . $row_code_create . $row_code_rename . $row_code_description . $row_code_force_push;
?>
				<tr class="<?=$row_code?>">
					<td class="clickable" 
						onclick="toggleRepoDetails('<?=$git_name?>')">
						<span id="git_name_<?=$git_name?>" class="changeable" 
							ondblclick="this.classList.toggle('hidden'); 
										document.getElementById('git_name_edit_<?=$git_name?>').classList.toggle('hidden'); 
										document.getElementById('git_name_new_<?=$git_name?>').focus();">
							<b><?=$git_name?></b>
						</span>
						<form action="" method="post" class="hidden" id="git_name_edit_<?=$git_name?>">
							<input type="hidden" name="action" value="rename_git" />
							<input type="hidden" name="git_name" value="<?=$git_name?>" />
							<input type="text" name="git_name_new" value="<?=$git_name?>" id="git_name_new_<?=$git_name?>" style="width: 150px;" 
								onkeydown="if (event.keyCode == 27) { 
											document.getElementById('git_name_<?=$git_name?>').classList.toggle('hidden'); 
											document.getElementById('git_name_edit_<?=$git_name?>').classList.toggle('hidden'); }" /> &nbsp; 
							<input type="submit" value="&check;" title="Git Repository umbenennen" class="button" 
								onclick="return confirm('Die URL in lokalen Kopien müssen angepasst werden!\nDas Git Repository `<?=$git_name?>` wirklich in `' + document.getElementById('git_name_new_<?=$git_name?>').value + '` umbenennen?');" />
						</form>
					</td>
					<td class="clickable" 
						onclick="toggleRepoDetails('<?=$git_name?>')">
						<?=($is_empty ? "<i>(leer)</i> &nbsp; " : "") . "\n"?>
						<span id="git_description_<?=$git_name?>" class="changeable" 
							ondblclick="this.classList.toggle('hidden'); 
										document.getElementById('git_descrption_edit_<?=$git_name?>').classList.toggle('hidden'); 
										document.getElementById('git_description_new<?=$git_name?>').focus();">
							<?=empty($git_description) ? "<i>(keine Beschreibung)</i>" : $git_description?> 
						</span>
						<form action="" method="post" class="hidden" id="git_descrption_edit_<?=$git_name?>">
							<input type="hidden" name="action" value="edit_git_descrption" />
							<input type="hidden" name="git_name" value="<?=$git_name?>" />
							<input type="text" name="git_description_new" value="<?=$git_description?>" id="git_description_new<?=$git_name?>" style="width: 500px;" 
								onkeydown="if (event.keyCode == 27) { 
											document.getElementById('git_description_<?=$git_name?>').classList.toggle('hidden'); 
											document.getElementById('git_descrption_edit_<?=$git_name?>').classList.toggle('hidden'); }" /> &nbsp; 
							<input type="submit" value="&check;" title="Git Repository Beschreibung ändern" class="button" 
								onclick="return confirm('Die Beschreibung des Git Repositories `<?=$git_name?>` wirklich ändern?');" />
						</form>
					</td>
					<td style="text-align: right;">
						<form action="" method="post">
							<input type="hidden" name="action" value="delete_git" />
							<input type="hidden" name="git_name" value="<?=$git_name?>" />
							<input type="submit" value="&cross;" title="Git Repository löschen" class="button" 
								onclick="return confirm('Das Git Repository `<?=$git_name?>` wirklich löschen?');" />
						</form>
					</td>
				</tr>
				<tr class="hidden<?=$row_code?>" id="git_details_row_<?=$git_name?>">
					<td></td>
					<td colspan="2">
						<div class="max-width-column" id="git_details_<?=$git_name?>">
<?php
		if ($is_empty) {
?>
							<p>
								Neues/leeres Projekt:<br />
								<span class="indented"><i>Git Bash</i> dort starten, wo das Git Verzeichnis heruntergeladen werden soll.</span>
								<span class="code select_all">git clone <?=$git_url?></span>
							</p>
							<p>
								Existierendes Verzeichnis:<br />
								<span class="indented"><i>Git Bash</i> in dem existierenden Verzeichnis starten.</span>
								<span class="code select_all"><?=
									"git init<br />" .
									"git remote add origin " . $git_url . "<br />" .
									"git add .<br />" .
									"git commit -m \"Initial commit\"<br />" .
									"git push -u origin master"
								?></span>
							</p>
							<p>
								Existierendes lokales Git Repository:<br />
								<span class="indented"><i>Git Bash</i> in dem existierenden Verzeichnis starten.</span>
								<span class="code select_all"><?=
									"git remote add origin " . $git_url . "<br />" .
									"git push -u origin --all<br />" .
									"git push -u origin --tags"
								?></span>
							</p>
							<p>
								Existierendes online Git Repository:<br />
								<span class="indented"><i>Git Bash</i> in dem existierenden Verzeichnis starten.</span>
								<span class="code select_all"><?=
									"git remote rename origin old<br />" .
									"git remote add origin " . $git_url . "<br />" .
									"git push -u origin --all<br />" .
									"git push -u origin --tags"
								?></span>
							</p>
<?php
		}
?>
						</div>
					</td>
				</tr>



<?php
	}
?>
			</table>
<?php
}
?>
		</section>
<?php
show_footer();
?>
