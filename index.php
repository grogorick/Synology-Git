<?php

define("CONFIG_SSH_USER", "***REMOVED***");
define("CONFIG_SSH_PASS_MD5", "***REMOVED***"); 
define("CONFIG_SSH_PORT", 22);
define("CONFIG_GIT_BASE_PATH", "/volume1/git/");



function show_header() {
?>
<html>
	<head>
		<title>Schatzkiste</title>
		<style>
			section {
				padding-top: 20px;
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
			.code {
				font-family: consolas;
				font-size: 80%;
				border-left: 1px solid #ddd;
				padding-left: 20px;
				display: inline-block;
			}
			.indented {
				display: block;
				padding-left: 20px;
			}
			.hidden {
				display: none;
			}
			.clickable {
				cursor: pointer;
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
			.existing_repos > tbody > tr > td {
				padding: 5px 20px;
			}
		</style>
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
	if (time() - $_SESSION['auth'] > 1800) {
		session_unset();
	}
}
if (!isset($_SESSION["auth"])) {
	if (isset($_POST["user"]) && isset($_POST["pass"]) && $_POST["user"] === CONFIG_SSH_USER && md5($_POST["pass"]) === CONFIG_SSH_PASS_MD5) {
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
				<input type="text" name="user" placeholder="Nutzer" />
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



function msg($text) {
	return "<section><span class=\"message\">$text</span></section>\n";
}

function check_git_name($git_name) {
	if (strlen($git_name) < 3) {
		return "Der gewählte Name ist zu kurz: mindestens 3 Zeichen.";
	}
	else if (!preg_match("/^[A-Za-z0-9_+\-]+$/", $git_name)) {
		return "Der gewählte Name enthält unerlaubte Zeichen. Benutze nur: A-Z a-z 0-9 _ + -";
	}
	return true;
}

function sipl($number, $singular, $plural) {
	return $number . " " . ($number == 1 ? $singular : $plural);
}



show_header();
?>
		
		
		
		<section>
			<?=$_SESSION["user"]?> &nbsp; <a href="/?logout" class="button">ausloggen</a>
		</section>

		
		
		
		<section>
			Neues Git Repository mit folgendem Namen erstellen: &nbsp; 
			<form action="" method="post">
				<input type="hidden" name="action" value="create_git" />
				<input type="text" name="git_name" placeholder="Git Name" /> &nbsp; 
				<input type="text" name="git_description" placeholder="Beschreibung" /> &nbsp; 
				<input type="submit" value="erstellen" class="button" />
			</form>
		</section>
		
		
		
<?php
if (isset($_POST["action"])) {
	if ($_POST["action"] === "create_git") {
		$git_name_create = trim(strip_tags($_POST["git_name"]));
		$check_name = check_git_name($git_name_create);
		if ($check_name !== true) {
			unset($git_name_create);
			echo 
"		" . msg($check_name);
		}
		else {
			$git_dir = escapeshellarg($git_name_create . ".git");
			$create_git_description = trim(strip_tags($_POST["git_description"]));
			echo 
"		" . msg("Neues Git Repository wird erstellt... <b>$git_name_create</b><br />" . 
				"<i>" . shell(cd_git . "mkdir $git_dir;" . "chgrp git $git_dir;" . "cd $git_dir;" . "git init --bare --shared;" . "echo $create_git_description > description;") . "</i>");
		}
	}
	else if ($_POST["action"] === "rename_git") {
		$git_name_rename = trim(strip_tags($_POST["git_name_new"]));
		$check_name = check_git_name($git_name_rename);
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
				"<i>" . cd_git . "mv $git_dir $git_dir_rename;" . shell(cd_git . "mv $git_dir $git_dir_rename;") . "</i>");
		}
	}
	else if ($_POST["action"] === "edit_git_descrption") {
		$git_name_description = $_POST["git_name"];
		$git_description = trim($_POST["git_description_new"]);
		$git_dir = escapeshellarg($git_name_description . ".git");
		echo 
"		" . msg("Beschreibung des Git Repositories  <b>$git_name_description</b> wird geändert...<br />" . 
				"<i>" . shell(cd_git . "cd $git_dir;" . "echo $git_description > description;") . "</i>");
	}
	else if ($_POST["action"] === "delete_git") {
		$git_name = $_POST["git_name"];
		$git_dir = escapeshellarg($git_name . ".git");
		echo 
"		" . msg("Git Repository wird gelöscht... <b>$git_name</b><br />" . 
				"<i>" . shell(cd_git . "rm -r $git_dir;") . "</i>");
	}
}
?>
		
		
		
		<section>
			Git Repositories:<br />
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
			<table class="existing_repos" style="margin-top: 10px;">
<?php
	$second_row = true;
	foreach ($git_repos as $git_name) {
		$git_dir = $git_name . ".git";
		$git_url = "ssh://" . CONFIG_SSH_USER . "@" . $_SERVER["SERVER_NAME"] . ":" . CONFIG_SSH_PORT . CONFIG_GIT_BASE_PATH . $git_name . ".git";
		$git_description = shell(cd_git . "cat $git_dir/description;");
		$is_empty = shell(cd_git . "cd $git_dir/refs/heads;" . count_files) == "0";
		
		$row_code_second = ($second_row = !$second_row) ? " second_row" : "";
		$row_code_create = ($git_name_create === $git_name) ? " new" : "";
		$row_code_rename = ($git_name_rename === $git_name) ? " modified" : "";
		$row_code_description = ($git_name_description === $git_name) ? " modified" : "";
		$row_code = $row_code_second . $row_code_create . $row_code_rename . $row_code_description;
?>
				<tr class="<?=$row_code?>">
					<td class="clickable" 
						onclick="document.getElementById('git_details_<?=$git_name?>').classList.toggle('hidden');">
						<span id="git_name_<?=$git_name?>" class="changeable" 
							ondblclick="this.classList.toggle('hidden'); 
										document.getElementById('git_name_edit_<?=$git_name?>').classList.toggle('hidden'); 
										document.getElementById('git_name_new_<?=$git_name?>').focus();">
							<b><?=$git_name?></b>
						</span>
						<form action="" method="post" class="hidden" id="git_name_edit_<?=$git_name?>">
							<input type="hidden" name="action" value="rename_git" />
							<input type="hidden" name="git_name" value="<?=$git_name?>" />
							<input type="text" name="git_name_new" value="<?=$git_name?>" id="git_name_new_<?=$git_name?>" style="width: 100px;" 
								onkeydown="if (event.keyCode == 27) { 
											document.getElementById('git_name_<?=$git_name?>').classList.toggle('hidden'); 
											document.getElementById('git_name_edit_<?=$git_name?>').classList.toggle('hidden'); }" /> &nbsp; 
							<input type="submit" value="&check;" title="Git Repository umbenennen" class="button" 
								onclick="return confirm('Die URL in lokalen Kopien müssen angepasst werden!\nDas Git Repository `<?=$git_name?>` wirklich in `' + document.getElementById('git_name_new_<?=$git_name?>').value + '` umbenennen?');" />
						</form>
					</td>
					<td class="clickable" 
						onclick="document.getElementById('git_details_<?=$git_name?>').classList.toggle('hidden');">
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
				<tr class="hidden<?=$row_code?>" id="git_details_<?=$git_name?>">
					<td></td>
					<td colspan="2">
<?php
		if ($is_empty) {
?>
						<p>
							Ein komplett neues Projekt starten:<br />
							<span class="code">
								git clone <?=$git_url?><br />
								cd test<br />
								touch README.md<br />
								git add README.md<br />
								git commit -m "add README"<br />
								git push -u origin master
							</span>
						</p>
						<p>
							Einen existierenden Ordner hochladen:<br />
							<span class="code">
								cd existing_folder<br />
								git init<br />
								git remote add origin <?=$git_url?><br />
								git add .<br />
								git commit -m "Initial commit"<br />
								git push -u origin master
							</span>
						</p>
						<p>
							Ein existierendes (lokales) Git Repository hochladen:<br />
							<span class="code">
								cd existing_repo<br />
								git remote add origin <?=$git_url?><br />
								git push -u origin --all<br />
								git push -u origin --tags
							</span>
						</p>
<?php
		}
		else {
			$num_commits = shell(cd_git . "cd $git_dir;" . "git rev-list --all --count;");
			$num_latest_commits = 3;
			$log = explode("\n", shell(cd_git . "cd $git_dir;" . "git log -$num_latest_commits --oneline --branches --all --source --format=format:'%C(bold blue)%h%C(reset) - %C(green)(%ar)%C(reset) %C(white)%s%C(reset) %C(dim white)- %an%C(reset)%C(auto)%d%C(reset)';"));
			$branches = explode("\n", shell(cd_git . "cd $git_dir;" . "git branch;"));
			$tags = explode("\n", shell(cd_git . "cd $git_dir;" . "git tag;"));
?>
						<p>
							<span class="code"><?=$git_url?></span>
						</p>
						<p>
							<?=sipl(count($branches), "Branch", "Branches")?>:
							<span class="indented">
								<?=implode("<br />", $branches)?> 
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
							<span class="indented">
<?php
			foreach ($log as &$commit) {
				$commit = preg_split("/\e\\[(\\d;)?(\\d){0,2}m/", $commit, -1, PREG_SPLIT_NO_EMPTY);
				$commit = $commit[4] . "<span style='color: gray;'> &nbsp; &mdash; &nbsp; " . str_replace("- ", "", $commit[6]) . " &nbsp; " . $commit[2] . " &nbsp; " . $commit[7] . " &nbsp; " . $commit[0] . "</span>";
			}
			echo 
"								" . implode("<br />\n" . 
"								", $log) . "\n";
?>
								<?=$num_commits > $num_latest_commits ? "<br />..." : ""?> 
							</span>
						</p>
<?php
		}
?>
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
