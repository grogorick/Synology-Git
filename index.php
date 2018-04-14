<?php

/*
Required Apps:
	- PHP 7.0
	- Web Station
	- Git Server
*/



define("CONFIG_LOGIN_USER", "***REMOVED***");
define("CONFIG_LOGIN_PASS_MD5", "***REMOVED***");

define("CONFIG_SERVER", "git.yournicedyndnsdomain.com");
define("CONFIG_SSH_USER", "gituser");
define("CONFIG_SSH_PORT", 22);
define("CONFIG_GIT_BASE_PATH", "/volume1/git/");

define("CONFIG_SESSION_TIMEOUT", 1800);
define("CONFIG_NUM_LATEST_COMMITS", 5);



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
define("ssh_git", "ssh git@localhost ");

function lines($str) {
	return array_filter(explode("\n", $str));
}

function readable_filesize($filesize) {
	for ($i = 0; ($filesize / 1024) > 0.9; $i++, $filesize /= 1024) {}
	return round($filesize, 2 /* precision */).['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
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
				$git_dir = $git_name . ".git";
				$git_url = "ssh://" . CONFIG_SSH_USER . "@" . CONFIG_SERVER . ":" . CONFIG_SSH_PORT . CONFIG_GIT_BASE_PATH . $git_name . ".git";
				
				$num_commits = shell(cd_git . "cd $git_dir;" . "git rev-list --all --count");
				$log = lines(shell(cd_git . "cd $git_dir;" . "git log -" . CONFIG_NUM_LATEST_COMMITS . " --graph --oneline --branches --all --date-order --format=format:'___%h___%ar___%s___%an___%d'"));
				$branches = lines(shell(cd_git . "cd $git_dir;" . "git branch"));
				$tags = lines(shell(cd_git . "cd $git_dir;" . "git tag"));
				$allow_force_push = preg_match("/=false$/i", shell(cd_git . "cd $git_dir;" . "git config --local -l | grep -i receive.denynonfastforwards"));
?>
				<p>
					<span class="code select_all">git clone <?=$git_url?></span>
				</p>
				<p>
					<?=sipl(count($branches), "Branch", "Branches")?>:
					<span class="indented">
<?php
				foreach ($branches as $i => $branch_disp) {
					if ($branch_disp[0] === "*")
						$branch = substr($branch_disp, 2);
					else
						$branch = $branch_disp;
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
					<?=sipl(count($tags), "Tag", "Tags")?>:
					<span class="indented">
						<?=implode("<br />", $tags)?> 
					</span>
				</p>
				<p>
					<?=sipl($num_commits, "Commit", "Commits")?>:
					<table class="commits">
<?php
				foreach ($log as &$commit) {
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
					else {
?>
						<tr><td><?=$commit[0 /* graph */]?></td></tr>
<?php
					}
				}
?>
						<tr><td><?=$num_commits > CONFIG_NUM_LATEST_COMMITS ? "..." : ""?></td></tr>
					</table>
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
			
			case "file-browser": {
				$git_name = urldecode($_GET["git_name"]);
				$git_dir = $git_name . ".git";
				$ref = urldecode($_GET["ref"]);
				$files = lines(shell(cd_git . "cd $git_dir;" . "git ls-tree -l --abbrev $ref"));
				foreach ($files as $i => $file) {
					$f = preg_split("/\\s+/", $file);
					if ($f[1] === "blob" /* file */) {
						$responseSpanId = "file_browser_" . $git_name . "_" . $ref . $f[4];
						$filesize = readable_filesize(intval($f[3]));
?>
							<span class="clickable" onclick="toggleFileContent(arguments[0], '<?=$git_name?>', '<?=$ref . $f[4]?>', '<?=$responseSpanId?>')"><?=$f[4]?><i>(<?=$filesize?>)</i></span>
							<span class="file-browser hidden" id="<?=$responseSpanId?>"></span>
<?php
					}
					else if ($f[1] === "tree" /* directory */) {
						$responseSpanId = "file_browser_" . $git_name . "_" . $ref . $f[4] . "/";
?>
							<span class="clickable" onclick="toggleFileBrowser(arguments[0], '<?=$git_name?>', '<?=$ref . $f[4] . "/"?>', '<?=$responseSpanId?>')"><?=$f[4]?></span>
							<span class="file-browser hidden" id="<?=$responseSpanId?>"></span>
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
				$git_dir = $git_name . ".git";
				$ref = urldecode($_GET["ref"]);
				$file_content = shell(cd_git . "cd $git_dir;" . "git show $ref");
				$file_content = htmlspecialchars($file_content);
				if ($file_content == "") {
					$file_content = "<i class='indented'>(Dieses Dateiformat kann nicht angezeigt werden)</i>";
				} else {
					$file_content = explode("\n", $file_content);
					$file_content = array_map(function($line, $n) { return "<tr><td>" . ($n + 1) . "</td><td>" . str_replace(array(" ", "\t"), array("&nbsp;", "&nbsp;&nbsp;"), $line) . "</td></tr>"; }, $file_content, array_keys($file_content));
					$file_content = "<table class='file_content'>" . implode("", $file_content) . "</table>";
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
		<style>
			section {
				padding-top: 20px;
			}
			div {
				margin: 0px;
				padding: 0px;
			}
			h1 {
				color: #666;
				font-size: 100%;
				font-weight: bold;
				margin-top: 0px;
				margin-bottom: 10px;
			}
			p {
				padding-top: 10px;
			}
			form {
				display: inline-block;
				margin: 0px;
			}
			table {
				border-collapse: separate;
				border-spacing: 0px;
			}
			table td {
				vertical-align: top;
			}
			input[type="text"], input[type="password"] {
				background: transparent;
				border: 0px;
				border-top: 1px solid #ddd;
				border-bottom: 1px solid #ddd;
			}
			a {
				color: #000;
				text-decoration: none;
			}
			hr {
				border: 0px;
				height: 1px;
				background: #ddd;
				margin-top: 20px;
				margin-bottom: 20px;
			}
			.button {
				background: transparent;
				border: 0px;
				border-left: 1px solid #ddd;
				border-right: 1px solid #ddd;
				padding: 0px 5px;
				cursor: pointer;
			}
			.button:hover {
				border-left: 1px solid #000;
				border-right: 1px solid #000;
			}
			.message {
				display: inline-block;
				border: 1px solid #ddd;
				padding: 10px 30px;
			}
			.inline-code {
				font-family: consolas;
				font-size: 80%;
				padding-left: 20px;
				padding-right: 20px;
			}
			.code {
				font-family: consolas;
				font-size: 80%;
				border-left: 1px solid #ddd;
				padding-left: 20px;
				margin-top: 5px;
				display: inline-block;
			}
			.indented {
				display: block;
				padding-left: 20px;
				margin-top: 5px;
			}
			.hidden {
				display: none !important;
			}
			.clickable {
				cursor: pointer;
			}
			.select_all {
				user-select: all;
			}
			.second_row {
				background: #fbfbfb;
			}
			.new {
				background: #dfd;
			}
			.modified {
				background: #ffb;
			}
			.break-lines {
				word-wrap: break-word;
			}
			.max-width-column {
				width: 900px;
				max-width: 900px;
			}
			.table_padding > tbody > tr > td {
				padding: 5px 20px;
			}
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
			table.file_content td:first-child {
				padding-right: 10px;
				border-right: 1px solid gray;
				color: gray;
				user-select: none;
			}
			table.file_content td:nth-child(2) {
				padding-left: 10px;
			}
		</style>
		<script>
			function requrest(url, responseTarget) {
				var xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function() {
					if (this.readyState == 4 && this.status == 200) {
						responseTarget.innerHTML = this.responseText;
					}
				};
				xhttp.open("GET", url, true);
				xhttp.send();
			}
			
			function togglePublicKeys() {
				var keys_table = document.getElementById("public_keys_table");
				if (keys_table.innerHTML.trim() == "") {
					requrest("/?request=list-public-keys", keys_table);
					keys_table.innerHTML = "<i>(lädt ...)</i>";
				}
				document.getElementById('public_key_management').classList.toggle('hidden');
			}
			
			function toggleRepoDetails(git_name) {
				var git_details_div = document.getElementById("git_details_" + git_name);
				if (git_details_div.innerHTML.trim() == "") {
					requrest("/?request=repo-details&git_name=" + encodeURI(git_name), git_details_div);
					git_details_div.innerHTML = "<i>(lädt ...)</i>";
				}
				document.getElementById("git_details_row_" + git_name).classList.toggle('hidden');
			}
			
			function toggleFileBrowser(event, git_name, ref, responseTargetId) {
				event.stopPropagation();
				var responseTarget = document.getElementById(responseTargetId);
				if (responseTarget.innerHTML.trim() == "") {
					requrest("/?request=file-browser&git_name=" + encodeURI(git_name) + "&ref=" + encodeURI(ref), responseTarget);
					responseTarget.innerHTML = "<i>(lädt ...)</i>";
				}
				responseTarget.classList.toggle('hidden');
			}
			
			function toggleFileContent(event, git_name, ref, responseTargetId) {
				event.stopPropagation();
				console.log(responseTargetId);
				var responseTarget = document.getElementById(responseTargetId);
				if (responseTarget.innerHTML.trim() == "") {
					requrest("/?request=file-content&git_name=" + encodeURI(git_name) + "&ref=" + encodeURI(ref), responseTarget);
					responseTarget.innerHTML = "<i>(lädt ...)</i>";
				}
				responseTarget.classList.toggle('hidden');
			}
		</script>
	</head>
	<body>
<?php
}

function show_footer() {
?>
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
	if (isset($_POST["user"]) && isset($_POST["pass"]) && $_POST["user"] === CONFIG_LOGIN_USER && md5($_POST["pass"]) === CONFIG_LOGIN_PASS_MD5) {
		session_regenerate_id(true);
		$_SESSION["user"] = $_POST["user"];
		$_SESSION['auth'] = time();
		header("Location: /");
		exit;
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
	header("Location: /");
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
				onclick="document.getElementById('synology_setup_instructions').classList.toggle('hidden');">
				Synology NAS Einrichtung
			</span>
			<span class="button" style="float: right;" 
				onclick="togglePublicKeys()">
				Public Keys
			</span>
			<hr />
		</section>

		
		
		<div id="synology_setup_instructions" class="hidden">
			<section id="synology_setup_instructions_short">
				<h1>Einrichtung</h1>
				<ul>
					<li>
						Erstelle einen neuen <i>Gemeinsamen Ordner</i> <b>git</b> und gib der Gruppe <i>http</i> die Berechtigungen <i>Lesen/Schreiben</i>.
					</li>
					<li>
						Erstelle einen neuen <i>Benutzer</i> <b>git</b> und füge ihn der Gruppe <i>http</i> zu.
					</li>
					<li>
						Dem Benutzer in der <i>Git Server</i> App den Zugriff für den Nutzer <b>git</b> erlauben.
					</li>
				</ul>
				
				<span class="button" 
					onclick="document.getElementById('synology_setup_instructions_short').classList.add('hidden'); 
							document.getElementById('synology_setup_instructions_long').classList.remove('hidden');">detaillierte Anleitung anzeigen</span>
				<hr />
			</section>
			
			<section id="synology_setup_instructions_long" class="hidden">
				<h1>Einrichtung</h1>
				<br />
				Zuerst wird ein Basisordner benötigt, in dem alle Git-Verzeichnisse angelegt werden sollen:
				<ul>
					<li>In der App <i>Systemsteuerung</i> die Kategorie <i>Gemeinsamer Ordner</i> wählen und den Button <i>Erstellen</i> nutzen.</li>
					<ul>
						<li>Name: <b>git</b></li>
						<li>[ &nbsp; ] Verbergen sie diesen gemenisamen Ordner unter "Netzwerkumgebung"</li>
						<li>[&check;] Unterordner und Dateien vor Benutzern ohne Berechtigungen ausblenden</li>
						<li>[ &nbsp; ] Papierkorb aktivieren</li>
						<li>[ &nbsp; ] Diesen gemeinsamen Ordner verschlüsseln</li>
					</ul>
					<li><i>OK</i>.</li>
				</ul>
				Das Fenster wechselt automatisch zu <i>Freigegebenen Ordner <b>git</b> bearbeiten</i>, in den Tab <i>Berechtigungen</i>.<br />
				Dort muss der Zugriff für die Web-Oberfläche freigegeben werden:
				<ul>
					<li>Filter von <i>Lokale Benutzer</i> zu <i>Lokale Gruppen</i> wechseln.</li>
					<li>Der Gruppe <i>http</i> die Berechtigungen zum <i>Lesen/Schreiben</i> [&check;] aktivieren.</li>
					<li><i>OK</i>.</li>
				</ul>
				
				Nun wird ein Nutzer für den externen Zugriff per Git benötigt:
				<ul>
					<li>In der App <i>Systemsteuering</i> die Kategorie <i>Benutzer</i> wählen und den Button <i>Erstellen</i> nutzen.</li>
					<ul>
						<li>Name: <b>git</b></li>
						<li>[&check;] Lassen Sie nicht zu, dass der Benutze das Konto-Passwort ändern kann.</li>
					</ul>
					<li><i>Weiter</i></li>
					<li>Im folgenden Fenster <i>Gruppen beitreten</i> die Gruppe <i>http</i> [&check;] aktivieren.</li>
					<li><i>Weiter</i></li>
					<li>Im folgenden Fenster <i>Berechtigungen für gemeinsame Ordner zuweisen</i> für den gemeinsamen Ordner <i>web</i> die Spalte <i>Kein Zugriff</i> [&check;] aktivieren.</li>
					<li><i>2 x Weiter</i></li>
					<li>Im folgenden Fenster <i>Anwendungsberechtigungen zuweisen</i> für alle Anwendungen <i>Verweigern</i> [&check;] aktivieren.</li>
					<li><i>2 x Weiter</i></li>
					<li><i>Übernehmen</i></li>
				</ul>
				
				Zuletzt muss der externe Zugriff per Git für diesen Nutzer noch zugelassen werden:
				<ul>
					<li>In der App <i>Git Server</i> den Zugriff für Nutzer <i>git</i> [&check;] erlauben.</li>
					<li><i>Übernehmen</i></li>
				</ul>
				
				<span class="button" 
					onclick="document.getElementById('synology_setup_instructions_long').classList.add('hidden'); 
							document.getElementById('synology_setup_instructions_short').classList.remove('hidden');">kurze Anleitung anzeigen</span>
				<hr />
			</section>
		</div>
		
		

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
		$key_idx = $_POST["key_idx"];
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
			Neues Git Repository mit folgendem Namen erstellen: &nbsp; 
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
$git_repos = array_map(git_dir_trim, array_filter(explode("\n", $list), git_dir_filter));

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
								<span class="code select_all">
									git clone <?=$git_url?> 
								</span>
							</p>
							<p>
								Existierendes Verzeichnis:<br />
								<span class="indented"><i>Git Bash</i> in dem existierenden Verzeichnis starten.</span>
								<span class="code select_all">
									git init<br />
									git remote add origin <?=$git_url?><br />
									git add .<br />
									git commit -m "Initial commit"<br />
									git push -u origin master
								</span>
							</p>
							<p>
								Existierendes lokales Git Repository:<br />
								<span class="indented"><i>Git Bash</i> in dem existierenden Verzeichnis starten.</span>
								<span class="code select_all">
									git remote add origin <?=$git_url?><br />
									git push -u origin --all<br />
									git push -u origin --tags
								</span>
							</p>
							<p>
								Existierendes online Git Repository:<br />
								<span class="indented"><i>Git Bash</i> in dem existierenden Verzeichnis starten.</span>
								<span class="code select_all">
									git remote rename origin old<br />
									git remote add origin <?=$git_url?><br />
									git push -u origin --all<br />
									git push -u origin --tags
								</span>
							</p>
<?php
		}
?>
						</div>
					</td>
				</tr>
			
			
			
<?php
	}
	echo 
"			</table>\n";
}
?>
		</section>
<?php
show_footer();
?>
