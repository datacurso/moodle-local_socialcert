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

$string['ai_actioncall'] = 'Создайте профессиональный текст для публикации в LinkedIn одним кликом';
$string['ai_field_heading'] = 'Текст публикации';
$string['aidisabled'] = 'ИИ-помощник отключён на этом сайте, поэтому текст публикации создать нельзя.';
$string['ailogoalt'] = 'Логотип Datacurso';
$string['airegionlabel'] = 'ИИ-помощник для подготовки публикации';
$string['airesponsebtn'] = 'Активировать ИИ';
$string['avatarlabel'] = 'Фотография профиля {$a}';
$string['buttonlabelshare'] = 'Поделиться в LinkedIn';
$string['certerror'] = "Вам необходимо иметь выданный сертификат, прежде чем вы сможете поделиться им в LinkedIn.";
$string['certerrordownload'] = 'Сначала получите сертификат: скачайте его на этой странице, чтобы им можно было поделиться в LinkedIn.';
$string['certerrornoorg'] = 'Публикация в LinkedIn пока недоступна: администратор сайта должен указать ID организации.';
$string['certificate_url'] = 'Ссылка';
$string['certificateimage'] = 'certificate.png';
$string['copyarticlebuttontext'] = 'Скопировать публикацию LinkedIn';
$string['copyconfirmation'] = 'Скопировано ✔';
$string['copytextlabel'] = 'Скопировать созданный текст';
$string['description'] = 'Позволяет пользователю поделиться своим сертификатом напрямую в LinkedIn.';
$string['enableai'] = 'Включить ИИ для предложения текста публикации';
$string['enableai_desc'] = 'Если параметр отключён, плагин не будет обращаться к Provider AI и не будет создавать предложения текста для публикаций в LinkedIn.';
$string['errorcredits'] = 'Недостаточно кредитов ИИ. Перейдите в раздел <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Управление кредитами</a> в магазине Datacurso, чтобы выделить или приобрести больше кредитов. Или свяжитесь с вашим администратором.';
$string['errorgeneric'] = 'При создании контента произошла ошибка. Пожалуйста, попробуйте позже.';
$string['errorlicense'] = 'Ваша лицензия не имеет права выполнять этот запрос. Управляйте своими лицензиями и кредитами в разделе <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Управление кредитами</a> в магазине Datacurso.';
$string['eventaitextgenerated'] = 'Текст публикации создан с помощью ИИ';
$string['eventcertificateshared'] = 'Сертификат опубликован в социальной сети';
$string['generating'] = 'Генерация…';
$string['invalidcmid'] = 'Идентификатор элемента курса в запросе некорректен.';
$string['linkcertbuttontext'] = 'Поделиться в LinkedIn';
$string['linktext'] = 'Ссылка на сертификат';
$string['nocertificateissued'] = 'Чтобы создать текст публикации, в этом элементе курса требуется выданный сертификат.';
$string['noissue'] = 'У вас пока нет выданного сертификата для этого курса.';
$string['notacertificateactivity'] = 'Запрошенный элемент курса не является настраиваемым сертификатом.';
$string['nothingtoshare'] = 'Для этого сертификата пока нечем поделиться.';
$string['organizationid'] = 'ID организации LinkedIn';
$string['organizationid_desc'] = 'Числовой идентификатор компании или организации, используемый функцией LinkedIn Add-to-Profile. Оставьте поле пустым, чтобы отключить до настройки.';
$string['organizationname'] = 'Название организации в LinkedIn';
$string['organizationname_desc'] = 'Название организации, отображаемое в LinkedIn. Должно точно соответствовать тому, как оно указано на LinkedIn. Оставьте поле пустым, чтобы отключить до настройки.';
$string['pluginname'] = 'Публикация сертификата с ИИ';
$string['popupblocked'] = 'Разрешите всплывающие окна, чтобы продолжить.';
$string['privacy:metadata'] = 'Плагин Share Certificate AI не хранит никаких персональных данных.';
$string['privacy:metadata:aiservice'] = 'Данные сертификата и курса, передаваемые в сервис ИИ Datacurso для подготовки текста публикации.';
$string['privacy:metadata:aiservice:certname'] = 'Название сертификата, о котором составляется публикация.';
$string['privacy:metadata:aiservice:coursename'] = 'Название курса, к которому относится сертификат.';
$string['privacy:metadata:aiservice:organizationname'] = 'Название организации-эмитента, указанное администратором сайта.';
$string['privacy:metadata:aiservice:sitedata'] = 'Идентификатор сайта, URL сайта и часовой пояс, используемые для авторизации запроса.';
$string['privacy:metadata:aiservice:userid'] = 'Числовой идентификатор пользователя, запросившего создание текста.';
$string['privacy:metadata:linkedin'] = 'Данные удостоверения, передаваемые в LinkedIn, когда пользователь публикует сертификат в своём профиле.';
$string['privacy:metadata:linkedin:certificationname'] = 'Название сертификации, добавляемой в профиль LinkedIn.';
$string['privacy:metadata:linkedin:credentialcode'] = 'Код удостоверения выданного сертификата.';
$string['privacy:metadata:linkedin:issuedate'] = 'Месяц и год выдачи сертификата.';
$string['privacy:metadata:linkedin:organizationid'] = 'Числовой идентификатор организации LinkedIn, указанный администратором сайта.';
$string['privacy:metadata:linkedin:verificationlink'] = 'Публичная ссылка для проверки сертификата.';
$string['sharecompleted'] = 'Публикация в LinkedIn завершена.';
$string['shareinstruction'] = 'Отпразднуйте своё достижение! Нажмите ниже, чтобы показать свой сертификат в LinkedIn и поделиться успехом с вашей сетью:';
$string['sharenowavailable'] = 'Ваш сертификат выдан, теперь вы можете поделиться им в LinkedIn.';
$string['sharesubtitle'] = 'Мы опубликуем проверяемую ссылку на ваш сертификат.';
$string['sharetitle'] = 'Поделитесь своим достижением в LinkedIn';
$string['socialcert:useaiassistant'] = 'Использовать ИИ-помощник для подготовки текста публикации';
$string['socialcert:viewsharepanel'] = 'Просматривать панель публикации сертификата в социальных сетях';
$string['verifywarning'] = 'Проверка сертификатов отключена, поэтому опубликованную в LinkedIn ссылку третьи лица не смогут проверить.';
$string['whatsharelabel'] = 'Что мы публикуем?';
