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

$string['ai_actioncall'] = 'Créez en un clic un message professionnel pour votre publication LinkedIn';
$string['ai_field_heading'] = 'Texte de la publication';
$string['aidisabled'] = 'L’assistant IA est désactivé sur ce site ; aucun texte de publication ne peut donc être généré.';
$string['ailogoalt'] = 'Logo de Datacurso';
$string['airegionlabel'] = 'Assistant IA pour rédiger votre publication';
$string['airesponsebtn'] = 'Activer l’IA';
$string['avatarlabel'] = 'Photo de profil de {$a}';
$string['buttonlabelshare'] = 'Partager sur LinkedIn';
$string['certerror'] = "Vous devez avoir un certificat délivré avant de pouvoir le partager sur LinkedIn.";
$string['certerrordownload'] = 'Obtenez d’abord votre certificat : téléchargez-le depuis cette page pour pouvoir le partager sur LinkedIn.';
$string['certerrornoorg'] = 'Le partage sur LinkedIn n’est pas encore disponible : l’administrateur du site doit configurer l’ID d’organisation.';
$string['certificate_url'] = 'Lien';
$string['certificateimage'] = 'certificate.png';
$string['copyarticlebuttontext'] = 'Copier l’article LinkedIn';
$string['copyconfirmation'] = 'Copié ✔';
$string['copytextlabel'] = 'Copier le texte généré';
$string['description'] = 'Permet à l’utilisateur de partager son certificat directement sur LinkedIn.';
$string['enableai'] = 'Activer l’IA pour suggérer le texte de la publication';
$string['enableai_desc'] = 'Si cette option est désactivée, le plug-in n’appellera pas Provider AI et ne générera aucune suggestion pour les publications LinkedIn.';
$string['errorcredits'] = 'Crédits d’IA insuffisants. Veuillez visiter <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gérer les crédits</a> dans la boutique Datacurso pour allouer ou acheter davantage de crédits. Ou contactez votre administrateur.';
$string['errorgeneric'] = 'Une erreur est survenue lors de la génération du contenu. Veuillez réessayer plus tard.';
$string['errorlicense'] = 'Votre licence n’est pas autorisée à effectuer cette requête. Veuillez gérer vos licences et crédits dans <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gérer les crédits</a> dans la boutique Datacurso.';
$string['eventaitextgenerated'] = 'Texte de publication généré par l’IA';
$string['eventcertificateshared'] = 'Certificat partagé sur un réseau social';
$string['generating'] = 'Génération en cours…';
$string['invalidcmid'] = 'L’identifiant d’activité de la requête n’est pas valide.';
$string['linkcertbuttontext'] = 'Partager sur LinkedIn';
$string['linktext'] = 'Lien du certificat';
$string['nocertificateissued'] = 'Vous devez disposer d’un certificat délivré dans cette activité avant de pouvoir générer un texte de publication.';
$string['noissue'] = 'Vous n’avez pas encore reçu de certificat pour ce cours.';
$string['notacertificateactivity'] = 'L’activité demandée n’est pas un certificat personnalisé.';
$string['nothingtoshare'] = 'Il n’y a encore rien à partager pour ce certificat.';
$string['organizationid'] = 'ID d’organisation LinkedIn';
$string['organizationid_desc'] = 'Identifiant numérique de l’entreprise/organisation utilisé par LinkedIn Add-to-Profile. Laissez vide pour désactiver jusqu’à ce que la configuration soit terminée.';
$string['organizationname'] = 'Nom de l’organisation LinkedIn';
$string['organizationname_desc'] = 'Nom de l’organisation à afficher sur LinkedIn. Doit correspondre exactement à celui utilisé sur LinkedIn. Laissez vide pour désactiver jusqu’à la configuration.';
$string['pluginname'] = 'Partager le certificat avec l’IA';
$string['popupblocked'] = 'Activez les fenêtres pop-up pour continuer.';
$string['privacy:metadata'] = 'Le plugin Share Certificate AI ne stocke aucune donnée personnelle.';
$string['privacy:metadata:aiservice'] = 'Données du certificat et du cours envoyées au service d’IA de Datacurso pour rédiger le texte de la publication.';
$string['privacy:metadata:aiservice:certname'] = 'Le nom du certificat au sujet duquel la publication est rédigée.';
$string['privacy:metadata:aiservice:coursename'] = 'Le nom du cours auquel appartient le certificat.';
$string['privacy:metadata:aiservice:organizationname'] = 'Le nom de l’organisation émettrice configuré par l’administrateur du site.';
$string['privacy:metadata:aiservice:sitedata'] = 'L’identifiant du site, l’URL du site et le fuseau horaire utilisés pour autoriser la requête.';
$string['privacy:metadata:aiservice:userid'] = 'L’identifiant numérique de l’utilisateur qui demande la génération.';
$string['privacy:metadata:linkedin'] = 'Données de la certification envoyées à LinkedIn lorsque l’utilisateur partage le certificat sur son profil.';
$string['privacy:metadata:linkedin:certificationname'] = 'Le nom de la certification ajoutée au profil LinkedIn.';
$string['privacy:metadata:linkedin:credentialcode'] = 'Le code de la certification délivrée.';
$string['privacy:metadata:linkedin:issuedate'] = 'Le mois et l’année de délivrance du certificat.';
$string['privacy:metadata:linkedin:organizationid'] = 'L’identifiant numérique de l’organisation LinkedIn configuré par l’administrateur du site.';
$string['privacy:metadata:linkedin:verificationlink'] = 'Le lien public de vérification du certificat.';
$string['sharecompleted'] = 'Partage LinkedIn terminé.';
$string['shareinstruction'] = 'Félicitez-vous ! Cliquez ci-dessous pour mettre en avant votre certificat sur LinkedIn et partager votre réussite avec votre réseau :';
$string['sharenowavailable'] = 'Votre certificat a été délivré ; vous pouvez donc le partager sur LinkedIn.';
$string['sharesubtitle'] = 'Nous publierons un lien vérifiable vers votre certificat.';
$string['sharetitle'] = 'Partagez votre réussite sur LinkedIn';
$string['socialcert:useaiassistant'] = 'Utiliser l’assistant IA pour rédiger le texte de la publication';
$string['socialcert:viewsharepanel'] = 'Consulter le panneau de partage du certificat sur les réseaux sociaux';
$string['verifywarning'] = 'La vérification des certificats est désactivée ; le lien publié sur LinkedIn ne sera donc pas vérifiable par des tiers.';
$string['whatsharelabel'] = 'Que partageons-nous ?';
