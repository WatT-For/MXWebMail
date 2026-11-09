<?php
declare(strict_types=1);
/** English dictionary of the sso-header plugin. */
return [
	'plugin.sso-header.label' => 'Proxy başlığıyla SSO',
	'plugin.sso-header.description' => 'Kullanıcı başlığına güvenen kimlik doğrulamalı reverse proxy (Authelia, authentik, oauth2-proxy) üzerinden otomatik giriş - IMAP ana parolası ve YALNIZCA proxy arkasında erişilebilen bir webmail gerektirir',
	'plugin.sso-header.denied' => 'SSO kimlik doğrulaması başarısız veya süresi doldu - tekrar deneyin veya manuel formu kullanın',
	'plugin.sso-header.f.header' => 'Güvenilir başlık',
	'plugin.sso-header.f.header.help' => 'Kimlik proxy\'si tarafından konulan başlık (varsayılan X-Forwarded-User ; Apache değişkeni için REMOTE_USER)',
	'plugin.sso-header.f.trusted_ips' => 'Güvenilir proxy IP\'leri (zorunlu)',
	'plugin.sso-header.f.trusted_ips.help' => 'CIDR listesi, satır başına bir tane - doğrudan eş eşleşmeli. BOŞ = eklenti devre dışı (fail-closed). Örnek, proxy atlatılarak ERİŞİLEMEMELİ olmalı',
	'plugin.sso-header.f.master_pass' => 'IMAP ana parolası (zorunlu)',
	'plugin.sso-header.f.master_pass.help' => 'Diğer gizli değerler gibi eklenti yapılandırmasında saklanır. "same" modu (Dovecot passdb master=yes): her kullanıcının parolası gibi çalışır',
	'plugin.sso-header.f.master_user' => 'Ana kullanıcı (isteğe bağlı)',
	'plugin.sso-header.f.master_user.help' => 'Ayarlanırsa IMAP/SMTP/Sieve girişi user*master olur ("separate" modu) - yalnızca birincil SSO oturumu için',
	'plugin.sso-header.f.allowed_domains' => 'İzin verilen alan adları',
	'plugin.sso-header.f.allowed_domains.help' => 'Jokerler, satır başına bir tane (örn. *@domain.tld). Boş = tümü',
	'plugin.sso-header.f.hide_form' => 'Parola formunu daralt',
	'plugin.sso-header.f.hide_form.help' => 'Manuel giriş bir bağlantının arkasında erişilebilir kalır - asla kaldırılmaz (yedek)',
	'plugin.sso-header.manual_login' => 'Manuel giriş',
];
