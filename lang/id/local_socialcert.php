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

$string['ai_actioncall'] = 'Buat pesan profesional untuk unggahan LinkedIn Anda dengan satu klik';
$string['ai_field_heading'] = 'Teks unggahan';
$string['aidisabled'] = 'Asisten AI dinonaktifkan untuk situs ini, sehingga teks unggahan tidak dapat dibuat.';
$string['ailogoalt'] = 'Logo Datacurso';
$string['airegionlabel'] = 'Asisten AI untuk menyusun unggahan Anda';
$string['airesponsebtn'] = 'Aktifkan AI';
$string['avatarlabel'] = 'Foto profil {$a}';
$string['buttonlabelshare'] = 'Bagikan di LinkedIn';
$string['certerror'] = "Anda perlu memiliki sertifikat yang diterbitkan sebelum dapat membagikannya di LinkedIn.";
$string['certerrordownload'] = 'Dapatkan sertifikat Anda terlebih dahulu: unduh sertifikat dari halaman ini agar dapat dibagikan di LinkedIn.';
$string['certerrornoorg'] = 'Berbagi di LinkedIn belum tersedia: administrator situs harus mengonfigurasi ID organisasi.';
$string['certificate_url'] = 'Tautan';
$string['certificateimage'] = 'certificate.png';
$string['copyarticlebuttontext'] = 'Salin artikel LinkedIn';
$string['copyconfirmation'] = 'Disalin ✔';
$string['copytextlabel'] = 'Salin teks yang dibuat';
$string['description'] = 'Memungkinkan pengguna untuk membagikan sertifikat mereka langsung di LinkedIn.';
$string['enableai'] = 'Aktifkan AI untuk menyarankan teks unggahan';
$string['enableai_desc'] = 'Jika dinonaktifkan, plugin tidak akan memanggil Provider AI dan tidak akan membuat saran untuk unggahan LinkedIn.';
$string['errorcredits'] = 'Kredit AI tidak mencukupi. Silakan kunjungi <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Kelola Kredit</a> di Toko Datacurso untuk mengalokasikan atau membeli kredit tambahan. Atau hubungi administrator Anda.';
$string['errorgeneric'] = 'Terjadi kesalahan saat membuat konten. Silakan coba lagi nanti.';
$string['errorlicense'] = 'Lisensi Anda tidak diizinkan untuk melakukan permintaan ini. Kelola lisensi dan kredit Anda di <a href="https://shop.datacurso.com/index.php?m=tokens_manager" target="_blank">Kelola Kredit</a> di Toko Datacurso.';
$string['eventaitextgenerated'] = 'Teks unggahan dibuat dengan AI';
$string['eventcertificateshared'] = 'Sertifikat dibagikan di jejaring sosial';
$string['generating'] = 'Sedang membuat…';
$string['invalidcmid'] = 'Pengenal aktivitas pada permintaan tidak valid.';
$string['linkcertbuttontext'] = 'Bagikan di LinkedIn';
$string['linktext'] = 'Tautan sertifikat';
$string['nocertificateissued'] = 'Anda memerlukan sertifikat yang diterbitkan pada aktivitas ini sebelum dapat membuat teks unggahan.';
$string['noissue'] = 'Anda belum memiliki sertifikat yang diterbitkan untuk kursus ini.';
$string['notacertificateactivity'] = 'Aktivitas yang diminta bukan sertifikat khusus.';
$string['nothingtoshare'] = 'Belum ada apa pun yang dapat dibagikan untuk sertifikat ini.';
$string['organizationid'] = 'ID organisasi LinkedIn';
$string['organizationid_desc'] = 'ID numerik perusahaan/organisasi yang digunakan oleh LinkedIn Add-to-Profile. Biarkan kosong untuk menonaktifkan hingga dikonfigurasi.';
$string['organizationname'] = 'Nama organisasi LinkedIn';
$string['organizationname_desc'] = 'Nama organisasi yang akan ditampilkan di LinkedIn. Harus sama persis seperti yang terdaftar di LinkedIn. Biarkan kosong untuk menonaktifkan hingga dikonfigurasi.';
$string['pluginname'] = 'Bagikan Sertifikat dengan AI';
$string['popupblocked'] = 'Aktifkan pop-up untuk melanjutkan.';
$string['privacy:metadata'] = 'Plugin Share Certificate AI tidak menyimpan data pribadi apa pun.';
$string['privacy:metadata:aiservice'] = 'Data sertifikat dan kursus yang dikirim ke layanan AI Datacurso untuk menyusun teks unggahan.';
$string['privacy:metadata:aiservice:certname'] = 'Nama sertifikat yang menjadi topik unggahan.';
$string['privacy:metadata:aiservice:coursename'] = 'Nama kursus tempat sertifikat tersebut berasal.';
$string['privacy:metadata:aiservice:organizationname'] = 'Nama organisasi penerbit yang dikonfigurasi oleh administrator situs.';
$string['privacy:metadata:aiservice:sitedata'] = 'Pengenal situs, URL situs, dan zona waktu yang digunakan untuk mengotorisasi permintaan.';
$string['privacy:metadata:aiservice:userid'] = 'Pengenal numerik pengguna yang meminta pembuatan teks.';
$string['privacy:metadata:linkedin'] = 'Data kredensial yang dikirim ke LinkedIn ketika pengguna membagikan sertifikat di profilnya.';
$string['privacy:metadata:linkedin:certificationname'] = 'Nama sertifikasi yang ditambahkan ke profil LinkedIn.';
$string['privacy:metadata:linkedin:credentialcode'] = 'Kode kredensial dari penerbitan sertifikat.';
$string['privacy:metadata:linkedin:issuedate'] = 'Bulan dan tahun penerbitan sertifikat.';
$string['privacy:metadata:linkedin:organizationid'] = 'Pengenal numerik organisasi LinkedIn yang dikonfigurasi oleh administrator situs.';
$string['privacy:metadata:linkedin:verificationlink'] = 'Tautan verifikasi publik dari sertifikat.';
$string['sharecompleted'] = 'Berbagi di LinkedIn telah selesai.';
$string['shareinstruction'] = 'Rayakan pencapaian Anda! Klik di bawah ini untuk menampilkan sertifikat Anda di LinkedIn dan beri tahu jaringan Anda tentang keberhasilan Anda:';
$string['sharenowavailable'] = 'Sertifikat Anda telah diterbitkan, sehingga sekarang Anda dapat membagikannya di LinkedIn.';
$string['sharesubtitle'] = 'Kami akan memublikasikan tautan yang dapat diverifikasi ke sertifikat Anda.';
$string['sharetitle'] = 'Bagikan pencapaian Anda di LinkedIn';
$string['socialcert:useaiassistant'] = 'Menggunakan asisten AI untuk menyusun teks unggahan';
$string['socialcert:viewsharepanel'] = 'Melihat panel untuk membagikan sertifikat di jejaring sosial';
$string['verifywarning'] = 'Verifikasi sertifikat dinonaktifkan, sehingga tautan yang dipublikasikan di LinkedIn tidak dapat diverifikasi oleh pihak ketiga.';
$string['whatsharelabel'] = 'Apa yang kami bagikan?';
