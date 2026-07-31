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

$string['ai_actioncall'] = 'Crie uma mensagem profissional para a sua publicação no LinkedIn com um único clique';
$string['ai_field_heading'] = 'Texto da publicação';
$string['aidisabled'] = 'O assistente de IA está desativado neste site, portanto não é possível gerar o texto da publicação.';
$string['ailogoalt'] = 'Logotipo da Datacurso';
$string['airegionlabel'] = 'Assistente de IA para redigir a sua publicação';
$string['airesponsebtn'] = 'Ativar IA';
$string['avatarlabel'] = 'Foto de perfil de {$a}';
$string['buttonlabelshare'] = 'Compartilhar no LinkedIn';
$string['certerror'] = "Você precisa ter um certificado emitido antes de poder compartilhá-lo no LinkedIn.";
$string['certerrordownload'] = 'Obtenha primeiro o seu certificado: baixe-o nesta página para poder compartilhá-lo no LinkedIn.';
$string['certerrornoorg'] = 'O compartilhamento no LinkedIn ainda não está disponível: o administrador do site precisa configurar o ID da organização.';
$string['certificate_url'] = 'Link';
$string['certificateimage'] = 'certificate.png';
$string['copyarticlebuttontext'] = 'Copiar artigo do LinkedIn';
$string['copyconfirmation'] = 'Copiado ✔';
$string['copytextlabel'] = 'Copiar o texto gerado';
$string['description'] = 'Permite que o usuário compartilhe o seu certificado diretamente no LinkedIn.';
$string['enableai'] = 'Ativar IA para sugerir o texto da publicação';
$string['enableai_desc'] = 'Se desativado, o plugin não chamará o Provider AI e não gerará sugestões para publicações no LinkedIn.';
$string['errorcredits'] = 'Créditos de IA insuficientes. Visite <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gerenciar créditos</a> na loja Datacurso para alocar ou comprar mais créditos. Ou entre em contato com seu administrador.';
$string['errorgeneric'] = 'Ocorreu um erro ao gerar o conteúdo. Tente novamente mais tarde.';
$string['errorlicense'] = 'Sua licença não tem permissão para realizar esta solicitação. Gerencie suas licenças e créditos em <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Gerenciar créditos</a> na loja Datacurso.';
$string['eventaitextgenerated'] = 'Texto de publicação gerado com IA';
$string['eventcertificateshared'] = 'Certificado compartilhado em uma rede social';
$string['generating'] = 'Gerando…';
$string['invalidcmid'] = 'O identificador da atividade da solicitação não é válido.';
$string['linkcertbuttontext'] = 'Compartilhar no LinkedIn';
$string['linktext'] = 'Link do certificado';
$string['nocertificateissued'] = 'Você precisa de um certificado emitido nesta atividade antes de poder gerar o texto da publicação.';
$string['noissue'] = 'Você ainda não tem um certificado emitido para este curso.';
$string['notacertificateactivity'] = 'A atividade solicitada não é um certificado personalizado.';
$string['nothingtoshare'] = 'Ainda não há nada para compartilhar deste certificado.';
$string['organizationid'] = 'ID da organização no LinkedIn';
$string['organizationid_desc'] = 'ID numérico da empresa/organização usado pelo LinkedIn Add-to-Profile. Deixe em branco para desativar até que seja configurado.';
$string['organizationname'] = 'Nome da organização no LinkedIn';
$string['organizationname_desc'] = 'Nome da organização a ser exibido no LinkedIn. Deve corresponder exatamente ao nome utilizado no LinkedIn. Deixe em branco para desativar até que seja configurado.';
$string['pluginname'] = 'Compartilhar Certificado com IA';
$string['popupblocked'] = 'Ative as janelas pop-up para continuar.';
$string['privacy:metadata'] = 'O plugin Share Certificate AI não armazena nenhum dado pessoal.';
$string['privacy:metadata:aiservice'] = 'Dados do certificado e do curso enviados ao serviço de IA da Datacurso para redigir o texto da publicação.';
$string['privacy:metadata:aiservice:certname'] = 'O nome do certificado sobre o qual a publicação é redigida.';
$string['privacy:metadata:aiservice:coursename'] = 'O nome do curso ao qual o certificado pertence.';
$string['privacy:metadata:aiservice:organizationname'] = 'O nome da organização emissora configurado pelo administrador do site.';
$string['privacy:metadata:aiservice:sitedata'] = 'O identificador do site, a URL do site e o fuso horário utilizados para autorizar a solicitação.';
$string['privacy:metadata:aiservice:userid'] = 'O identificador numérico do usuário que solicita a geração.';
$string['privacy:metadata:linkedin'] = 'Dados da credencial enviados ao LinkedIn quando o usuário compartilha o certificado em seu perfil.';
$string['privacy:metadata:linkedin:certificationname'] = 'O nome da certificação adicionada ao perfil do LinkedIn.';
$string['privacy:metadata:linkedin:credentialcode'] = 'O código da credencial da emissão do certificado.';
$string['privacy:metadata:linkedin:issuedate'] = 'O mês e o ano de emissão do certificado.';
$string['privacy:metadata:linkedin:organizationid'] = 'O identificador numérico da organização no LinkedIn configurado pelo administrador do site.';
$string['privacy:metadata:linkedin:verificationlink'] = 'O link público de verificação do certificado.';
$string['sharecompleted'] = 'Compartilhamento no LinkedIn concluído.';
$string['shareinstruction'] = 'Celebre a sua conquista! Clique abaixo para exibir o seu certificado no LinkedIn e contar à sua rede sobre o seu sucesso:';
$string['sharenowavailable'] = 'O seu certificado já foi emitido, portanto agora você pode compartilhá-lo no LinkedIn.';
$string['sharesubtitle'] = 'Publicaremos um link verificável do seu certificado.';
$string['sharetitle'] = 'Compartilhe a sua conquista no LinkedIn';
$string['socialcert:useaiassistant'] = 'Usar o assistente de IA para redigir o texto da publicação';
$string['socialcert:viewsharepanel'] = 'Ver o painel para compartilhar o certificado em redes sociais';
$string['verifywarning'] = 'A verificação de certificados está desativada, portanto o link publicado no LinkedIn não poderá ser verificado por terceiros.';
$string['whatsharelabel'] = 'O que compartilhamos?';
