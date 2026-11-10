# Veritabanı

*Fransızcadan çevrilmiştir - Fransızca sürüm esastır.*

MXWebMail **SQLite** (varsayılan) veya **MariaDB/MySQL** ile çalışır.
Sürücü `config.php` içinde `db_dsn` ile seçilir.

## 1. Bağlantı

- `db_dsn = 'sqlite'` > `app/storage/db/webmail.sqlite` (WAL modu +
  `foreign_keys`).
- Aksi halde tam PDO DSN (örn.
  `mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4`) ile
  `db_user`/`db_pass`.

PDO seçenekleri: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`,
`EMULATE_PREPARES=false`.

## 2. Geçişler

Şema SQLite/MariaDB arasında **taşınabilir** (otomatik artan birincil
anahtarlar sürücüye uyarlanmış). Geçişler sürümlenir (`meta.schema_version`)
ve veritabanına ilk erişimde (`App::db()`) otomatik uygulanır, kurulum
dahil.

Kod güncellemesinden sonra *Hakkında* sekmesi **sonuçlandırma**
(`/api/admin/update/finalize`) çalıştırır: yeni sürümün geçişleri admin
bağlamında yürütülür, ardından kullanıcı başına kişi veritabanları sırayla
geçirilir (hata veren taban günlüklenir ve sonraki erişimde yeniden
denenir). Yedek olarak: `php app/bin/post-update.php` geçişleri CLI'de
yeniden oynatır. Şema koddan yeniyse (tabanı geri yüklemeden dosya
geriletmesi), `meta.schema_ahead` konur ve *Hakkında*'da işaretlenir -
geçişler o zaman askıya alınır.

## 3. Genel tablolar

| Tablo | İçerik |
|---|---|
| `meta` | anahtar/değer çiftleri (`schema_version`, `contacts_moved`, …). |
| `users` | `username` (tekil), `display_name`, `is_admin`, `disabled`, `session_epoch` ("Oturumu zorla kapat" artırır - sonraki istekte aktif oturumları reddeder), `created_at`, `last_login_at`. |
| `accounts` | kullanıcı IMAP hesapları: `email`, `imap_user`, `imap_pass` (şifreli), `is_default` (ana hesap, Ayarlar > Hesaplar'dan değişir), `position` (ek hesapların görüntü sırası, Ayarlar > Hesaplar'da sürükle-bırak), `auth` (`password`, `google`, `microsoft`, `yahoo` - OAuth2: `imap_pass` o zaman şifreli *refresh token* saklar). |
| `oauth_apps` | kullanıcı başına OAuth2 uygulamaları: `provider`, `client_id`, `secret` (şifreli), `tenant` - `(user_id, provider)` başına bir uygulama (bkz. [domaines.md](domaines.md) §8). |
| `user_settings` | kullanıcı ayarları (anahtar/değer), PK `(user_id, key)`. |
| `identities` | gönderen kimlikleri: `name`, `email`, `reply_to`, `bcc`, `signature`, `is_default`. |
| `domains` | etki alanı başına posta sunucuları (bkz. [domaines.md](domaines.md)) + `options` JSON. |
| `plugins` | `enabled`, `user_allowed` (0 ise admin'e özel - varsayılan), `config` (JSON) eklenti başına. |
| `themes` | `config` (JSON) ve `allowed` tema başına. |
| `pgp_keys` | OpenPGP anahtarlığı (`key_data` armored, `is_private`). |
| `auth_tokens` | "Oturum açık kalsın" belirteçleri: `selector` (tekil), `token` (doğrulayıcının SHA-256'sı, her kullanımda döner), `agent` (User-Agent), `ip` (verilişte), `last_ip` (son geri yükleme), `created_at`, `last_used_at`, `expires_at`. |

Eski `contacts` / `contact_emails` tabloları genel tabandan çıkarıldı
(bkz. §5).

## 4. `app.key`

`app/storage/app.key` 32 baytlık ana anahtardır (64 hex), ilk kimlik bilgisi
şifrelemesinde üretilir. Şunları şifreler (XSalsa20-Poly1305, libsodium
secretbox):

- oturumdaki ve `accounts.imap_pass` içindeki IMAP parolaları
  (`auth != 'password'` hesaplarının OAuth2 *refresh token*'ları dahil);
- kullanıcı OAuth2 uygulama gizli anahtarları (`oauth_apps.secret`);
- eklenti gizli anahtarları (örn. CardDAV parolası).

**Yedekleyin ve asla kaybetmeyin**: kaybı tüm saklı kimlik bilgilerini
geçersiz kılar (kullanıcılar yeniden bağlanmak zorunda). Ne sunulmalı ne
sürümlenmeli.

## 5. Kullanıcı başına veritabanları (kişiler)

Kişiler bireysel SQLite tabanlarında izoledir:
`app/storage/db/users/<id>.sqlite` (`contacts`, `contact_emails` tabloları).
Bu **MariaDB altında da geçerli**: kişisel veriler SQLite dosyalarında kalır.

`contacts_moved` geçişi kişileri genel tabandan bu dosyalara bir kez
kopyalar (işlem + `INSERT OR IGNORE`, kendini onaran), ardından genel
tabloları siler.

## 6. SQLite'tan MariaDB'ye geçiş

1. Admin'de (*Veritabanı*) MariaDB DSN/host/port/ad/kullanıcı/parola
   girin. Bağlantı testi **zorunlu**dur ve taban yoksa onu oluşturur
   (`utf8mb4`, `utf8mb4_unicode_ci` collation).
2. Geçişler şemayı yeni tabanda yeniden oynatır.

> **Önemli**: `db_dsn` değişikliği **boş bir şema** ile başlar - veriler
> (kullanıcılar, etki alanları, ayarlar) **taşınmaz**. Dışa aktarma
> aracı yok. Buna karşılık kullanıcı başına kişi dosyaları değişmez kalır.

## 7. Yedekleme / geri yükleme

- SQLite: `app/storage/db/webmail.sqlite` (+ `db/users/*.sqlite`) ve
  `app.key`'i kopyalayın.
- MariaDB: tabanı `mysqldump` ile dökün, artı `app.key` ve
  `db/users/*.sqlite`.
