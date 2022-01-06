# Synology-Git
Git server web interface for small synology systems (that do not support things like Gitea via virtualization)

### Features
- Login via diskstation accounts (FileStation API)
- Create/delete repos
- Browse repo branches, directories and files
- List commits and tags
- Preview files (text)
- En-/disable force push per repo
- Manage (add/remove) ssh keys

### Required Packages
- Git Server *(Currently, there is an issue with the bundled git shell, and a work around using the community version. See https://community.synology.com/enu/forum/8/post/147518)*
- Web Server
- PHP 7.0+
- FileStation

### Setup (German)
- Zuerst wird ein Basisordner benötigt, in dem alle Git-Verzeichnisse angelegt werden sollen.
    - Erstelle einen neuen <i>Gemeinsamen Ordner</i> <b>git</b>:
        - In der App <i>Systemsteuerung</i> die Kategorie <i>Gemeinsamer Ordner</i> wählen und den Button <i>Erstellen</i> nutzen.
            - Name: <b>git</b>
            - [ &nbsp; ] Verbergen sie diesen gemenisamen Ordner unter "Netzwerkumgebung"
            - [&check;] Unterordner und Dateien vor Benutzern ohne Berechtigungen ausblenden
            - [ &nbsp; ] Papierkorb aktivieren
            - [ &nbsp; ] Diesen gemeinsamen Ordner verschlüsseln
        - <i>OK</i>.
    - Das Fenster wechselt automatisch zu <i>Freigegebenen Ordner <b>git</b> bearbeiten</i>, in den Tab <i>Berechtigungen</i>.<br />
    Dort muss der Zugriff für die Web-Oberfläche freigegeben werden:
        - Filter von <i>Lokale Benutzer</i> zu <i>Lokale Gruppen</i> wechseln.
        - Der Gruppe <i>http</i> die Berechtigungen zum <i>Lesen/Schreiben</i> [&check;] aktivieren.
        - <i>OK</i>.
- Nun wird ein Nutzer für den externen Zugriff per Git benötigt.
    - Erstelle einen neuen <i>Benutzer</i> <b>git</b> und füge ihn der Gruppe <i>http</i> zu.
        - In der App <i>Systemsteuering</i> die Kategorie <i>Benutzer</i> wählen und den Button <i>Erstellen</i> nutzen.
            - Name: <b>git</b>
            - [&check;] Lassen Sie nicht zu, dass der Benutzer das Konto-Passwort ändern kann.
        - <i>Weiter</i>
        - Im folgenden Fenster <i>Gruppen beitreten</i> die Gruppe <i>http</i> [&check;] aktivieren.
        - <i>Weiter</i>
        - Im folgenden Fenster <i>Berechtigungen für gemeinsame Ordner zuweisen</i> für den gemeinsamen Ordner <i>web</i> die Spalte <i>Kein Zugriff</i> [&check;] aktivieren.
        - <i>2 x Weiter</i>
        - Im folgenden Fenster <i>Anwendungsberechtigungen zuweisen</i> für alle Anwendungen <i>Verweigern</i> [&check;] aktivieren.
        - <i>2 x Weiter</i>
        - <i>Übernehmen</i>
    - Dem Benutzer in der <i>Git Server</i> App den Zugriff für den Nutzer <b>git</b> erlauben.
        - Jetzt muss der externe Zugriff per Git für diesen Nutzer noch zugelassen werden:
            - In der App <i>Git Server</i> den Zugriff für Nutzer <i>git</i> [&check;] erlauben.
            - <i>Übernehmen</i>
- Um SSH-Keys verwalten zu können, wird das PHP Plugin <i>openssl</i> in der <i>Web Station</i> App benötigt:
    - In der App <i>Web Station</i> auf <i>Allgemeine Einstellungen</i> wechseln und die genutzte <i>PHP</i> Version merken.
    - Auf <i>PHP-Einstellungen</i> wechseln.
    - Das genutzte Profil (gemerkte Version) <i>bearbeiten</i>.
    - Unten bei <i>Erweiterungen</i> den Eintrag <i>openssl</i> suchen und [&check;] aktivieren.

### Screenshot
![screenshot](docs/screen.png)