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

$string['ai_actioncall']   = 'Crea un mensaje profesional para tu publicación de LinkedIn con un solo clic';
$string['ai_field_heading'] = 'Texto del Post';
$string['aidisabled'] = 'El asistente de IA está desactivado en este sitio, por lo que no se puede generar el texto de la publicación.';
$string['airesponsebtn'] = 'Activar IA';
$string['buttonlabelshare']  = 'Compartir en LinkedIn';
$string['certerror'] = "Necesitas tener un certificado emitido antes de poder compartirlo en LinkedIn.";
$string['certificate_url']   = 'Enlace';
$string['certificateimage'] = 'certificado.png';
$string['copyconfirmation'] = 'Copiado ✔';
$string['description'] = 'Permite al usuario compartir su certificado directamente en LinkedIn.';
$string['enableai'] = 'Habilitar IA para sugerir texto del post';
$string['enableai_desc'] = 'Si se desmarca, el plugin no llamará a Provider AI ni generará sugerencias de texto para LinkedIn.';
$string['errorcredits'] = 'Créditos de IA insuficientes. Visita <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Administrar créditos</a> en la tienda de Datacurso para asignar o comprar más créditos. O contacta a tu administrador.';
$string['errorgeneric'] = 'Se produjo un error al generar el contenido. Por favor, inténtalo de nuevo más tarde.';
$string['errorlicense'] = 'Tu licencia no está autorizada para realizar esta solicitud. Administra tus licencias y créditos en <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Administrar créditos</a> en la tienda de Datacurso.';
$string['generating'] = 'Generando…';
$string['invalidcmid'] = 'El identificador de la actividad de la solicitud no es válido.';
$string['linkcertbuttontext'] = 'Compartir en LinkedIn';
$string['nocertificateissued'] = 'Necesitas un certificado emitido en esta actividad antes de poder generar el texto de la publicación.';
$string['noissue'] = 'Aún no tienes un certificado emitido para este curso.';
$string['notacertificateactivity'] = 'La actividad solicitada no es un Certificado personalizado.';
$string['organizationid'] = 'ID de organización en LinkedIn';
$string['organizationid_desc'] = 'ID numérico de la empresa/organización usado por LinkedIn Add-to-profile. Déjalo vacío para deshabilitar hasta configurarlo.';
$string['organizationname'] = 'Nombre de la organización en LinkedIn';
$string['organizationname_desc'] = 'Nombre de la organización que se mostrará en LinkedIn. Debe coincidir exactamente con cómo aparece en LinkedIn. Déjelo vacío para deshabilitar hasta que se configure.';
$string['pluginname'] = 'Share Certificate AI';
$string['popupblocked']      = 'Activa las ventanas emergentes para continuar.';
$string['postcertbuttontext'] = 'Publicar en LinkedIn';
$string['privacy:metadata'] = 'El complemento Share Certificate AI no almacena ningún dato personal.';
$string['privacy:metadata:aiservice'] = 'Datos del certificado y del curso enviados al servicio de IA de Datacurso para redactar el texto de la publicación.';
$string['privacy:metadata:aiservice:certname'] = 'El nombre del certificado sobre el que se redacta la publicación.';
$string['privacy:metadata:aiservice:coursename'] = 'El nombre del curso al que pertenece el certificado.';
$string['privacy:metadata:aiservice:organizationname'] = 'El nombre de la organización emisora configurado por el administrador del sitio.';
$string['privacy:metadata:aiservice:sitedata'] = 'El identificador del sitio, su URL y la zona horaria utilizados para autorizar la solicitud.';
$string['privacy:metadata:aiservice:userid'] = 'El identificador numérico del usuario que solicita la generación.';
$string['privacy:metadata:linkedin'] = 'Datos de la credencial enviados a LinkedIn cuando el usuario comparte el certificado en su perfil.';
$string['privacy:metadata:linkedin:certificationname'] = 'El nombre de la certificación que se añade al perfil de LinkedIn.';
$string['privacy:metadata:linkedin:credentialcode'] = 'El código de credencial de la emisión del certificado.';
$string['privacy:metadata:linkedin:issuedate'] = 'El mes y el año de emisión del certificado.';
$string['privacy:metadata:linkedin:organizationid'] = 'El identificador numérico de la organización de LinkedIn configurado por el administrador del sitio.';
$string['privacy:metadata:linkedin:verificationlink'] = 'El enlace público de verificación del certificado.';
$string['sharecompleted']    = 'Compartido en LinkedIn correctamente.';
$string['shareinstruction'] = '¡Celebra tu logro! Haz clic a continuación para mostrar tu certificado en LinkedIn y contarle a tu red sobre tu éxito:';
$string['sharesubtitle']     = 'Publicaremos un enlace verificable de tu certificado.';
$string['sharetitle']        = 'Comparte tu logro en LinkedIn';
$string['verifywarning'] = 'La verificación de certificados está desactivada, por lo que el enlace publicado en LinkedIn no será verificable por terceros.';
$string['whatsharelabel']    = '¿Qué compartimos?';
