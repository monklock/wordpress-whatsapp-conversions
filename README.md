# WordPress WhatsApp Conversions

[Русский](#русский) · [English](#english)

## Русский

Минимальный WordPress-плагин для подтверждённых WhatsApp-конверсий. Событие `whatsapp_lead` отправляется в Google Analytics 4 только после первого входящего текстового сообщения с действующим intent token, подтверждённого подписанным webhook Meta.

Открытие `/go-whatsapp`, запуск WhatsApp или открытие чата без отправки сообщения не считаются конверсией.

### Требования

- WordPress 6.0 или новее;
- PHP 8.0 или новее;
- MySQL, совместимый с текущей версией WordPress;
- публичный HTTPS URL для Meta webhook;
- WhatsApp Business Platform / Meta Cloud API;
- веб-поток GA4;
- WooCommerce необязателен. При его наличии фоновые задачи выполняются через Action Scheduler.

### Установка

1. Скопируйте каталог `wordpress-whatsapp-conversions` в `wp-content/plugins/`.
2. Добавьте обязательные константы в `wp-config.php` перед строкой `/* That's all, stop editing! */`.
3. Создайте или сохраните опубликованную страницу WordPress со slug `go-whatsapp`.
4. Активируйте **WordPress WhatsApp Conversions** в разделе «Плагины».
5. Убедитесь, что текущие ссылки используют формат `/go-whatsapp?from=header` с одним из поддерживаемых источников.

Плагин создаёт таблицу `{prefix}wwc_intents` при активации. Данные сохраняются при деактивации и удалении плагина.

### Конфигурация `wp-config.php`

```php
define( 'WWC_PHONE_NUMBER', 'replace-with-whatsapp-number' );
define( 'WWC_VERIFY_TOKEN', 'replace-with-a-long-random-token' );
define( 'WWC_APP_SECRET', 'replace-with-meta-app-secret' );

define( 'WWC_GA4_MEASUREMENT_ID', 'G-XXXXXXXXXX' );
define( 'WWC_GA4_API_SECRET', 'replace-with-ga4-api-secret' );
```

Не добавляйте реальные секреты в Git. Плагин не сохраняет эти значения в базе данных и не выводит их в админке.

Значения констант:

- `WWC_PHONE_NUMBER` - номер WhatsApp Business, на который открывается чат. Используйте международный формат, только цифры, без `+`, пробелов и дефисов, например `549XXXXXXXXXX`. Номер должен относиться к WhatsApp Business Account, для которого настроен webhook;
- `WWC_VERIFY_TOKEN` - придуманная вами длинная случайная строка. Это не access token Meta. Укажите одно и то же значение в `wp-config.php` и в настройках webhook Meta;
- `WWC_APP_SECRET` - App Secret приложения из **Meta App Dashboard → App settings → Basic**. Он используется только для проверки подписи входящих webhook-запросов;
- `WWC_GA4_MEASUREMENT_ID` - Measurement ID веб-потока GA4 в формате `G-XXXXXXXXXX`, доступный в **GA4 → Admin → Data streams → Web**;
- `WWC_GA4_API_SECRET` - Measurement Protocol API secret, созданный в настройках выбранного веб-потока GA4. Это не Measurement ID и не Google API key.

Плагину не требуются Meta access token и Phone Number ID: он не отправляет сообщения через Cloud API, а открывает `wa.me` и принимает подписанный webhook.

Если номер не задан или некорректен, `/go-whatsapp` возвращает HTTP 503. Если GA4 настроен некорректно, подтверждённый lead остаётся в БД со статусом доставки `failed`. При отсутствии GA Client ID lead сохраняется со статусом `skipped`.

### Поддерживаемые источники

`header`, `footer`, `product`, `cart`, `checkout`, `contact`, `mobile-menu`, `single-product`, `archive-product`, `unknown`.

Любое неизвестное значение `from` заменяется на `unknown`. Адрес назначения фиксирован на HTTPS-host `wa.me` и не принимается из запроса.

### Настройка для конкретного сайта

Добавьте фильтры в собственный плагин, mu-plugin или `functions.php` дочерней темы:

```php
add_filter( 'wwc_redirect_page_slug', static fn(): string => 'contact-whatsapp' );

add_filter(
	'wwc_whatsapp_message_text',
	static fn( string $message, string $source ): string => 'Здравствуйте! Хочу получить консультацию.',
	10,
	2
);

add_filter(
	'wwc_allowed_sources',
	static function ( array $sources ): array {
		$sources[] = 'campaign';

		return $sources;
	}
);
```

Для изменённого slug создайте опубликованную страницу с таким же slug и обновите ссылки. Плагин сам добавляет защищённый intent token к тексту сообщения - добавлять его в фильтре не нужно.

### Настройка Meta webhook

1. В [Meta for Developers](https://developers.facebook.com/) создайте приложение с вариантом использования **Connect with customers through WhatsApp** и привяжите его к Business Portfolio.
2. Добавьте продукт **WhatsApp**, затем в **WhatsApp → API Setup** выберите или создайте WhatsApp Business Account и подключите бизнес-номер. Для первоначальной проверки можно использовать тестовый номер Meta и разрешённый тестовый номер получателя.
3. Скопируйте App Secret из **App settings → Basic** в `WWC_APP_SECRET`. До настройки webhook добавьте в `wp-config.php` также `WWC_VERIFY_TOKEN`.
4. Откройте **WhatsApp → Configuration** или раздел **Webhooks** и укажите публичный callback URL:

   ```text
   https://example.com/wp-json/wordpress-whatsapp-conversions/v1/webhook
   ```

5. В поле **Verify token** укажите точное значение `WWC_VERIFY_TOKEN` и выполните проверку. URL должен быть доступен из интернета по HTTPS без Basic Auth, maintenance mode и блокировки запросов Meta.
6. В настройках полей webhook подпишитесь на поле `messages` для нужного WhatsApp Business Account.
7. Переведите приложение в режим **Live**, когда потребуется принимать сообщения не только от пользователей с ролями приложения и разрешённых тестовых номеров.
8. Отправьте сообщение на бизнес-номер с другого WhatsApp-аккаунта. Для проверки конверсии используйте ссылку `/go-whatsapp?from=header` и не удаляйте строку `Ref: WWC-XXXXXXXX` из подготовленного сообщения.

При сохранении webhook Meta отправляет GET verification, а плагин возвращает challenge как `text/plain`. Входящие POST-запросы Meta автоматически подписывает App Secret в заголовке `X-Hub-Signature-256`; плагин отклоняет запросы с неверной подписью. Payload без входящих текстовых сообщений безопасно игнорируется.

Успешная проверка: Meta принимает callback URL, webhook-запрос для отправленного сообщения получает HTTP 200, а соответствующий intent появляется в отчёте **WhatsApp Leads** со статусом `converted`.

### Настройка GA4 и Google Ads

1. Скопируйте Measurement ID веб-потока GA4 в `WWC_GA4_MEASUREMENT_ID`.
2. В настройках веб-потока создайте Measurement Protocol API secret и поместите его в `WWC_GA4_API_SECRET`.
3. После первого подтверждённого события найдите `whatsapp_lead` в GA4 и отметьте его как key event.
4. Свяжите GA4 с Google Ads и импортируйте key event `whatsapp_lead` как конверсию.

Google Ads API плагином не используется. При наличии WooCommerce отправка ставится в Action Scheduler; иначе создаётся одиночное событие WP-Cron. Webhook не ожидает ответа GA4. Автоматические повторы не выполняются; потерянные задания со статусом `pending` восстанавливаются ежедневным обслуживанием.

### Отчёт

При активном WooCommerce откройте **WooCommerce → WhatsApp Leads**. Без WooCommerce используйте **Инструменты → WhatsApp Leads**.

Отчёт показывает intents, подтверждённые обращения, уникальных отправителей, conversion rate и статусы GA4. Фильтр периода формирует cohort по `created_at`, то есть по времени создания intent. Последние 20 обращений не содержат PII.

### Безопасность и приватность

- текст сообщения и номер клиента не сохраняются;
- WhatsApp sender ID хранится только как keyed HMAC SHA-256;
- token, рекламные ID, sender hash и полный URL не передаются в GA4;
- рекламные ID читаются из query или уже существующих first-party cookies, но плагин не создаёт собственный глобальный cookie-механизм;
- подпись Meta проверяется через HMAC SHA-256 и constant-time comparison;
- повторная доставка webhook и повторные сообщения не создают дополнительную конверсию для того же intent;
- intents истекают через 7 дней;
- доступ к отчёту ограничен capability `manage_woocommerce` или `manage_options`.

### Ограничения версии 1.0

- обрабатываются только входящие текстовые сообщения;
- звонки, изображения, аудио, документы и interactive messages не считаются конверсиями;
- прямой Google Ads API и Enhanced Conversions не реализованы;
- автоматические повторы неуспешной отправки GA4 не выполняются;
- экран настройки секретов отсутствует - конфигурация хранится только в `wp-config.php`;
- плагин не управляет настройкой Meta, GA4, GTM или Google Ads;
- удаление плагина намеренно не удаляет таблицу с историей лидов.

---

## English

A minimal WordPress plugin for confirmed WhatsApp conversions. The `whatsapp_lead` event is sent to Google Analytics 4 only after the first incoming text message containing a valid intent token is confirmed by a signed Meta webhook.

Visiting `/go-whatsapp`, launching WhatsApp, or opening a chat without sending a message does not count as a conversion.

### Requirements

- WordPress 6.0 or newer;
- PHP 8.0 or newer;
- MySQL supported by the installed WordPress version;
- a public HTTPS URL for the Meta webhook;
- WhatsApp Business Platform / Meta Cloud API;
- a GA4 web data stream;
- WooCommerce is optional. When available, background jobs use Action Scheduler.

### Installation

1. Copy the `wordpress-whatsapp-conversions` directory to `wp-content/plugins/`.
2. Add the required constants to `wp-config.php` before `/* That's all, stop editing! */`.
3. Create or keep a published WordPress page with the `go-whatsapp` slug.
4. Activate **WordPress WhatsApp Conversions** under Plugins.
5. Make sure existing links use `/go-whatsapp?from=header` with a supported source.

The plugin creates the `{prefix}wwc_intents` table on activation. Data is preserved when the plugin is deactivated or deleted.

### `wp-config.php` configuration

```php
define( 'WWC_PHONE_NUMBER', 'replace-with-whatsapp-number' );
define( 'WWC_VERIFY_TOKEN', 'replace-with-a-long-random-token' );
define( 'WWC_APP_SECRET', 'replace-with-meta-app-secret' );

define( 'WWC_GA4_MEASUREMENT_ID', 'G-XXXXXXXXXX' );
define( 'WWC_GA4_API_SECRET', 'replace-with-ga4-api-secret' );
```

Never commit real secrets. The plugin does not store these values in the database or display them in WordPress Admin.

Constant values:

- `WWC_PHONE_NUMBER` - the WhatsApp Business number opened by the redirect. Use international digits only, without `+`, spaces, or hyphens, for example `1555XXXXXXX`. The number must belong to the WhatsApp Business Account configured for the webhook;
- `WWC_VERIFY_TOKEN` - a long random value that you create. It is not a Meta access token. Use the exact same value in `wp-config.php` and Meta webhook settings;
- `WWC_APP_SECRET` - the application's App Secret from **Meta App Dashboard → App settings → Basic**. It is used only to validate incoming webhook signatures;
- `WWC_GA4_MEASUREMENT_ID` - the GA4 web stream Measurement ID in `G-XXXXXXXXXX` format, available under **GA4 → Admin → Data streams → Web**;
- `WWC_GA4_API_SECRET` - the Measurement Protocol API secret created for the selected GA4 web stream. It is not the Measurement ID or a Google API key.

The plugin does not require a Meta access token or Phone Number ID. It does not send messages through Cloud API; it opens `wa.me` and receives signed webhooks.

An absent or invalid phone number makes `/go-whatsapp` return HTTP 503. Invalid GA4 configuration leaves the confirmed lead in the database with delivery status `failed`. A lead without a GA Client ID is preserved with status `skipped`.

### Supported sources

`header`, `footer`, `product`, `cart`, `checkout`, `contact`, `mobile-menu`, `single-product`, `archive-product`, `unknown`.

Any unknown `from` value becomes `unknown`. The destination is restricted to the fixed HTTPS host `wa.me` and is never accepted from request data.

### Site-specific customization

Add filters in a custom plugin, mu-plugin, or the child theme's `functions.php`:

```php
add_filter( 'wwc_redirect_page_slug', static fn(): string => 'contact-whatsapp' );

add_filter(
	'wwc_whatsapp_message_text',
	static fn( string $message, string $source ): string => 'Hello! I would like a consultation.',
	10,
	2
);

add_filter(
	'wwc_allowed_sources',
	static function ( array $sources ): array {
		$sources[] = 'campaign';

		return $sources;
	}
);
```

For a custom slug, create a published page with the same slug and update existing links. The plugin appends the protected intent token to the message automatically; do not add it in the filter.

### Meta webhook setup

1. In [Meta for Developers](https://developers.facebook.com/), create an app with the **Connect with customers through WhatsApp** use case and associate it with a Business Portfolio.
2. Add the **WhatsApp** product. Under **WhatsApp → API Setup**, select or create a WhatsApp Business Account and connect the business number. You can use Meta's test number and an allowed test recipient for the initial check.
3. Copy the App Secret from **App settings → Basic** to `WWC_APP_SECRET`. Add `WWC_VERIFY_TOKEN` to `wp-config.php` before configuring the webhook.
4. Open **WhatsApp → Configuration** or **Webhooks** and set the public callback URL:

   ```text
   https://example.com/wp-json/wordpress-whatsapp-conversions/v1/webhook
   ```

5. Enter the exact `WWC_VERIFY_TOKEN` value in **Verify token** and complete verification. The URL must be publicly reachable over HTTPS without Basic Auth, maintenance mode, or a firewall blocking Meta requests.
6. Subscribe the required WhatsApp Business Account to the `messages` webhook field.
7. Switch the app to **Live** when messages must be accepted from users other than app-role users and allowed test numbers.
8. Send a message to the business number from another WhatsApp account. To verify a conversion, start from `/go-whatsapp?from=header` and keep the `Ref: WWC-XXXXXXXX` line in the prepared message.

When the webhook is saved, Meta sends a GET verification request and the plugin returns the challenge as `text/plain`. Meta automatically signs incoming POST requests with the App Secret in `X-Hub-Signature-256`; the plugin rejects requests with an invalid signature. Payloads without incoming text messages are acknowledged and ignored.

A successful test means Meta accepts the callback URL, the webhook request for the sent message receives HTTP 200, and the matching intent appears as `converted` in **WhatsApp Leads**.

### GA4 and Google Ads setup

1. Copy the GA4 web stream Measurement ID to `WWC_GA4_MEASUREMENT_ID`.
2. Create a Measurement Protocol API secret in the web stream settings and set `WWC_GA4_API_SECRET`.
3. After the first confirmed event appears, mark `whatsapp_lead` as a GA4 key event.
4. Link GA4 to Google Ads and import the `whatsapp_lead` key event as a conversion.

The plugin does not use the Google Ads API. Delivery uses WooCommerce Action Scheduler when available and a single WP-Cron event otherwise. The webhook never waits for GA4. Automatic retries are disabled; daily maintenance recovers converted intents that remain `pending` because their job was lost.

### Report

With WooCommerce active, open **WooCommerce → WhatsApp Leads**. Without WooCommerce, open **Tools → WhatsApp Leads**.

The report shows intents, confirmed conversations, unique senders, conversion rate, and GA4 delivery statuses. Date filters build a cohort from intent `created_at`. The latest 20 rows contain no PII.

### Security and privacy

- message text and customer phone numbers are never stored;
- WhatsApp sender IDs are stored only as keyed HMAC SHA-256 values;
- tokens, ad IDs, sender hashes, and full URLs are never sent to GA4;
- ad IDs are read from query parameters or existing first-party cookies; the plugin does not add a second global cookie mechanism;
- Meta signatures are checked with HMAC SHA-256 and constant-time comparison;
- duplicate webhook deliveries and later messages cannot create another conversion for the same intent;
- intents expire after 7 days;
- report access requires `manage_woocommerce` or `manage_options`.

### Version 1.0 limitations

- only incoming text messages are processed;
- calls, images, audio, documents, and interactive messages do not count as conversions;
- direct Google Ads API and Enhanced Conversions are not implemented;
- failed GA4 requests are not retried automatically;
- there is no secrets settings screen; configuration lives only in `wp-config.php`;
- the plugin does not configure Meta, GA4, GTM, or Google Ads;
- uninstall intentionally preserves the lead history table.
