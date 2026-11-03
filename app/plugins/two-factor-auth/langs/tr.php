<?php
declare(strict_types=1);

/** Dictionnaire turc du plugin two-factor-auth. */
return [
	'plugin.two-factor-auth.description'        => 'TOTP iki faktörlü kimlik doğrulama (Google Authenticator, Aegis vb.)',
	'plugin.two-factor-auth.tab'                => 'İki faktörlü kimlik doğrulama',
	'plugin.two-factor-auth.issuer'             => 'TOTP uygulamasında gösterilen hizmet adı',
	'plugin.two-factor-auth.label'              => 'TOTP uygulamasında gösterilen hesap adı',
	'plugin.two-factor-auth.label.help'         => '"Tam adres" e-postayı korur; "Yerel bölüm" @alan adını kaldırır - bazı uygulamalar simgeyi alan adına göre seçer.',
	'plugin.two-factor-auth.label.email'        => 'Tam adres',
	'plugin.two-factor-auth.label.local'        => 'Yerel bölüm (@ öncesi)',
	'plugin.two-factor-auth.icon_url'           => 'Simge URL’si (image= parametresi)',
	'plugin.two-factor-auth.icon_url.help'      => 'Aegis/andOTP/FreeOTP standart dışı image parametresiyle gösterir - Authy yok sayar. Boş = kurulum favicon’u.',
	'plugin.two-factor-auth.title'              => 'İki faktörlü kimlik doğrulama (TOTP)',
	'plugin.two-factor-auth.secret'             => 'Gizli anahtar:',
	'plugin.two-factor-auth.code_placeholder'   => '6 haneli kod',
	'plugin.two-factor-auth.verify'             => 'Doğrula',
	'plugin.two-factor-auth.generate'           => 'Gizli anahtar oluştur',
	'plugin.two-factor-auth.disable'            => 'Devre dışı bırak',
	'plugin.two-factor-auth.status_on'          => 'Bu hesapta iki faktörlü kimlik doğrulama etkin.',
	'plugin.two-factor-auth.status_off'         => 'İki faktörlü kimlik doğrulama devre dışı.',
	'plugin.two-factor-auth.disable_hint'       => 'Devre dışı bırakmak için uygulamanızdan geçerli kodu girin ve "Doğrula"ya tıklayın.',
	'plugin.two-factor-auth.no_pending'         => 'Süren etkinleştirme yok',
	'plugin.two-factor-auth.code_invalid'       => 'Geçersiz kod',
	'plugin.two-factor-auth.code_invalid_disable' => 'Geçersiz kod - devre dışı bırakmak için geçerli kodu girin',
	'plugin.two-factor-auth.admin_title'          => 'Yönetici iki faktörlü kimlik doğrulama (TOTP)',
	'plugin.two-factor-auth.admin_local_only'     => 'Yerel yönetici hesabı için geçerlidir (admin_user).',
	'plugin.two-factor-auth.admin_no_local'       => 'Yerel yönetici hesabı yapılandırılmamış (admin_user/admin_password_hash) - ayar etkisiz olur.',
	'plugin.two-factor-auth.admin_config_readonly' => 'config.php yazılabilir değil - etkinleştirme mümkün değil.',
	'plugin.two-factor-auth.recovery_title'   => 'Kurtarma kodları',
	'plugin.two-factor-auth.recovery_regen'   => 'Kodları yeniden oluştur',
	'plugin.two-factor-auth.recovery_left'    => '{v} kurtarma kodu kaldı - tek kullanımlık, TOTP alanına girilir.',
];
