<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_socialcert
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_actioncall'] = 'Erstelle mit einem Klick einen professionellen Text für deinen LinkedIn-Beitrag';
$string['ai_field_heading'] = 'Beitragstext';
$string['aidisabled'] = 'Der KI-Assistent ist auf dieser Website deaktiviert, daher kann kein Beitragstext erstellt werden.';
$string['ailogoalt'] = 'Datacurso-Logo';
$string['airegionlabel'] = 'KI-Assistent zum Verfassen deines Beitrags';
$string['airesponsebtn'] = 'KI aktivieren';
$string['avatarlabel'] = 'Profilbild von {$a}';
$string['buttonlabelshare'] = 'Auf LinkedIn teilen';
$string['certerror'] = "Sie benötigen ein ausgestelltes Zertifikat, bevor Sie es auf LinkedIn teilen können.";
$string['certerrordownload'] = 'Hole dir zuerst dein Zertifikat: Lade es auf dieser Seite herunter, um es auf LinkedIn teilen zu können.';
$string['certerrornoorg'] = 'Das Teilen auf LinkedIn ist noch nicht verfügbar: Die Administration der Website muss die Organisations-ID konfigurieren.';
$string['certificate_url'] = 'Link';
$string['certificateimage'] = 'certificate.png';
$string['copyarticlebuttontext'] = 'LinkedIn-Beitrag kopieren';
$string['copyconfirmation'] = 'Kopiert ✔';
$string['copytextlabel'] = 'Den erstellten Text kopieren';
$string['description'] = 'Ermöglicht es dem Benutzer, sein Zertifikat direkt auf LinkedIn zu teilen.';
$string['enableai'] = 'KI aktivieren, um vorgeschlagenen Beitragstext zu erzeugen';
$string['enableai_desc'] = 'Wenn diese Option deaktiviert ist, ruft das Plugin Provider AI nicht auf und generiert keine Vorschläge für LinkedIn-Beiträge.';
$string['errorcredits'] = 'Unzureichende KI-Guthaben. Bitte besuchen Sie <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Guthaben verwalten</a> im Datacurso Shop, um weitere Guthaben zuzuweisen oder zu kaufen. Oder kontaktieren Sie Ihre Administratorin/Ihren Administrator.';
$string['errorgeneric'] = 'Beim Generieren des Inhalts ist ein Fehler aufgetreten. Bitte versuche es später erneut.';
$string['errorlicense'] = 'Ihre Lizenz ist nicht berechtigt, diese Anfrage auszuführen. Bitte verwalten Sie Ihre Lizenzen und Guthaben unter <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Guthaben verwalten</a> im Datacurso Shop.';
$string['eventaitextgenerated'] = 'Beitragstext mit KI erstellt';
$string['eventcertificateshared'] = 'Zertifikat in einem sozialen Netzwerk geteilt';
$string['generating'] = 'Wird erstellt…';
$string['invalidcmid'] = 'Die Aktivitätskennung der Anfrage ist nicht gültig.';
$string['linkcertbuttontext'] = 'Auf LinkedIn teilen';
$string['linktext'] = 'Zertifikatslink';
$string['nocertificateissued'] = 'Du benötigst ein ausgestelltes Zertifikat in dieser Aktivität, bevor du einen Beitragstext erstellen kannst.';
$string['noissue'] = 'Du hast noch kein Zertifikat für diesen Kurs erhalten.';
$string['notacertificateactivity'] = 'Die angeforderte Aktivität ist kein individuelles Zertifikat.';
$string['nothingtoshare'] = 'Für dieses Zertifikat gibt es noch nichts zu teilen.';
$string['organizationid'] = 'LinkedIn-Organisations-ID';
$string['organizationid_desc'] = 'Numerische ID der Firma/Organisation, die von LinkedIn Add-to-Profile verwendet wird. Leer lassen, um die Funktion zu deaktivieren, bis sie konfiguriert ist.';
$string['organizationname'] = 'Name der LinkedIn-Organisation';
$string['organizationname_desc'] = 'Name der Organisation, wie sie auf LinkedIn angezeigt wird. Muss exakt übereinstimmen. Leer lassen, um die Funktion zu deaktivieren, bis sie konfiguriert ist.';
$string['pluginname'] = 'Zertifikat mit KI teilen';
$string['popupblocked'] = 'Bitte Pop-ups aktivieren, um fortzufahren.';
$string['privacy:metadata'] = 'Das Share Certificate AI-Plugin speichert keine personenbezogenen Daten.';
$string['privacy:metadata:aiservice'] = 'Zertifikats- und Kursdaten, die an den KI-Dienst von Datacurso gesendet werden, um den Beitragstext zu verfassen.';
$string['privacy:metadata:aiservice:certname'] = 'Der Name des Zertifikats, über das der Beitrag verfasst wird.';
$string['privacy:metadata:aiservice:coursename'] = 'Der Name des Kurses, zu dem das Zertifikat gehört.';
$string['privacy:metadata:aiservice:organizationname'] = 'Der Name der ausstellenden Organisation, der von der Administration der Website konfiguriert wurde.';
$string['privacy:metadata:aiservice:sitedata'] = 'Die Kennung der Website, die Website-URL und die Zeitzone, die zur Autorisierung der Anfrage verwendet werden.';
$string['privacy:metadata:aiservice:userid'] = 'Die numerische Kennung des Benutzers, der die Erstellung anfordert.';
$string['privacy:metadata:linkedin'] = 'Nachweisdaten, die an LinkedIn gesendet werden, wenn der Benutzer das Zertifikat in seinem Profil teilt.';
$string['privacy:metadata:linkedin:certificationname'] = 'Der Name der Zertifizierung, die dem LinkedIn-Profil hinzugefügt wird.';
$string['privacy:metadata:linkedin:credentialcode'] = 'Der Nachweiscode der Zertifikatsausstellung.';
$string['privacy:metadata:linkedin:issuedate'] = 'Monat und Jahr der Ausstellung des Zertifikats.';
$string['privacy:metadata:linkedin:organizationid'] = 'Die numerische LinkedIn-Organisations-ID, die von der Administration der Website konfiguriert wurde.';
$string['privacy:metadata:linkedin:verificationlink'] = 'Der öffentliche Verifizierungslink des Zertifikats.';
$string['sharecompleted'] = 'Das Teilen auf LinkedIn wurde abgeschlossen.';
$string['shareinstruction'] = 'Feiere deinen Erfolg! Klicke unten, um dein Zertifikat auf LinkedIn zu präsentieren und dein Netzwerk an deinem Erfolg teilhaben zu lassen:';
$string['sharenowavailable'] = 'Dein Zertifikat wurde ausgestellt, du kannst es nun auf LinkedIn teilen.';
$string['sharesubtitle'] = 'Wir veröffentlichen einen verifizierbaren Link zu deinem Zertifikat.';
$string['sharetitle'] = 'Teile deinen Erfolg auf LinkedIn';
$string['socialcert:useaiassistant'] = 'Den KI-Assistenten zum Verfassen des Beitragstexts verwenden';
$string['socialcert:viewsharepanel'] = 'Den Bereich zum Teilen des Zertifikats in sozialen Netzwerken ansehen';
$string['verifywarning'] = 'Die Zertifikatsverifizierung ist deaktiviert, daher wird der auf LinkedIn veröffentlichte Link für Dritte nicht überprüfbar sein.';
$string['whatsharelabel'] = 'Was wird geteilt?';
