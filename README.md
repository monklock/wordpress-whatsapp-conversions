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

Если номер не задан или некорректен, `/go-whatsapp` возвращает HTTP 503. Если GA4 настроен некорректно, подтверждённый lead остаётся в БД со статусом доставки `failed`. При отсутствии GA Client ID lead сохраняется со статусом `skipped`.

### Поддерживаемые источники

`header`, `footer`, `product`, `cart`, `checkout`, `contact`, `mobile-menu`, `single-product`, `archive-product`, `unknown`.

Любое неизвестное значение `from` заменяется на `unknown`. Адрес назначения фиксирован на HTTPS-host `wa.me` и не принимается из запроса.

### Настройка Meta webhook

1. В приложении Meta добавьте продукт WhatsApp и откройте настройку Webhooks.
2. Укажите callback URL:

   ```text
   https://example.com/wp-json/wordpress-whatsapp-conversions/v1/webhook
   ```

3. Укажите то же значение verify token, что задано в `WWC_VERIFY_TOKEN`.
4. Подпишитесь на поле WhatsApp `messages` для нужного business account.
5. Проверьте, что Meta подписывает POST-запросы секретом приложения из `WWC_APP_SECRET`.

GET verification возвращает challenge как `text/plain`. POST принимается только с корректной подписью `X-Hub-Signature-256`. Payload без входящих текстовых сообщений безопасно игнорируется.

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

An absent or invalid phone number makes `/go-whatsapp` return HTTP 503. Invalid GA4 configuration leaves the confirmed lead in the database with delivery status `failed`. A lead without a GA Client ID is preserved with status `skipped`.

### Supported sources

`header`, `footer`, `product`, `cart`, `checkout`, `contact`, `mobile-menu`, `single-product`, `archive-product`, `unknown`.

Any unknown `from` value becomes `unknown`. The destination is restricted to the fixed HTTPS host `wa.me` and is never accepted from request data.

### Meta webhook setup

1. Add the WhatsApp product to the Meta app and open Webhooks configuration.
2. Set the callback URL:

   ```text
   https://example.com/wp-json/wordpress-whatsapp-conversions/v1/webhook
   ```

3. Enter the same verify token used in `WWC_VERIFY_TOKEN`.
4. Subscribe the required WhatsApp business account to the `messages` field.
5. Ensure Meta signs POST requests with the app secret configured as `WWC_APP_SECRET`.

GET verification returns the challenge as `text/plain`. POST requests require a valid `X-Hub-Signature-256`. Payloads without incoming text messages are acknowledged and ignored.

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
