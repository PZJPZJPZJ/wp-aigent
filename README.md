# WP AIgent

WP AIgent is an all-in-one AI toolkit for WordPress. It helps you run AI chatbots, answer visitor questions from your knowledge base, capture leads, send notifications, and enhance forms with AI-ready visitor context.

[![PHP](https://img.shields.io/badge/PHP-8.0+-%23777BB4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.7+-%2321759B.svg)](https://wordpress.org)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Features

### 🤖 Multi-Platform AI Engine
- **Reusable AI Providers** — create multiple encrypted provider connections once and share them across AI features
- **OpenAI** — GPT-4o, GPT-4, GPT-3.5-turbo, and any OpenAI-compatible API
- **Anthropic** — Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Haiku
- **Any compatible provider** — OpenRouter, DeepSeek, Azure OpenAI, and custom endpoints
- **Fallback model** — automatic retry with a secondary model when the primary fails
- **Extended Thinking / Reasoning** — `reasoning_effort` for OpenAI o-series and `thinking` for Anthropic Claude, with effort levels from low to max

### 📚 Knowledge Base Q&A
- Create Markdown documents as a knowledge base
- Bind any set of knowledge documents to each chatbot
- Full-text context injection — AI answers exclusively from your content
- Custom filter hook `ai_chatbot_knowledge_context` to modify injected context

### 🧠 Smart Lead Capture
- AI-powered lead scoring (A–E scale) based on conversation quality
- Define custom lead collection fields via the JSON Schema Builder
- Configurable trigger rules (OR/AND grouped conditions)
- Interactive contact form that appears when rules are satisfied
- Visitor data collection: name, email, WhatsApp, country, project type, and more

### 📊 Conversation Management
- Server-issued Visitor UUID with a 90-day rolling HttpOnly cookie
- Configurable session TTL (hours) before auto-rotation
- Full conversation history with timestamps, model names, and token usage
- Token usage tracking with support for cached tokens (OpenAI & Anthropic)
- AI-generated conversation summaries for long-term context
- Export conversations as Markdown or JSON

### 🔔 Notification System
- **Email notifications** — HTML formatted lead alerts (compatible with any WordPress mailer: SMTP, FluentSMTP, etc.)
- **WeCom (企业微信) webhook** — Markdown formatted push to group chat
- **Rule-based triggers** — OR/AND grouped conditions (same operators as lead capture)
- **Inactivity timeout** — Defer notification evaluation until the conversation has been idle for N hours (WP Cron-based)
- **Manual trigger** — Send notification on demand from the conversation admin screen
- **Notification history** — Full log with status tracking

### 🎨 Flexible Layout & Styling
- **Elementor-only widget** — place and style the chatbot from the Elementor editor
- **Box mode** — embed the chatbot directly in page content
- **Button mode** — popup opened from a visible action button
- **Elementor positioning** — use Elementor's built-in positioning controls for button placement
- **Custom color scheme** — independent header/popup and button colors
- **Button icon** — Font Awesome 4, Dashicons, or custom emoji
- **Ripple animation** — configurable color, opacity, speed, and radius
- **Icon shake** — subtle vibration effect for attention
- **Hint tooltip** — customizable position, colors, and font size
- **Auto-open** — popup opens on page load; configurable delay and cache TTL
- **Popup transition** — configurable fade-in/out duration (0–1000ms)
- **Live Elementor preview** — widget controls update the editor preview in real time

### 🔧 Admin Experience
- **Multi-bot management** — create individual chatbots with independent settings
- **Tabbed configuration** — API Provider, System Prompt, Knowledge, Memory, Lead Capture, Notifications
- **Model auto-fetch** — retrieves available models from the API automatically
- **Custom model entry** — manually enter any model name
- **JSON Schema Builder** — interactive UI to define structured lead data fields
- **Rule builders** — visual OR/AND grouped rule editors (notifications + lead capture)
- **Conversation viewer** — detailed read-only view with message history, lead data, token usage, and notification log
- **Admin columns** — quick overview of platform, model, lead score, and notification status
- **API Key encryption** — AES-256-CBC encrypted storage using WordPress salts
- **Rate limiting** — independent configurable limits per Visitor and per resolved client IP
- **Proxy-aware client IP** — deployment modes for origin servers, trusted reverse proxies, and Cloudflare proxy traffic

### 🔌 Integration
- **Elementor widget** — drag-and-drop integration with any Elementor page
- **AI Forms** — adds a Country Code field type to Elementor Forms with CF-IPCountry detection
- **REST API** — versionless `/ai-chat/visitor`, `/ai-chat/chat`, and `/ai-chat/history` endpoints
- **Auto-update** — GitHub Release updater built-in (Update URI support)
- **i18n-ready** — full text domain with customizable UI strings (title, subtitle, placeholder)

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.7+ |
| PHP | 8.0+ |
| Elementor | Active plugin |
| AI API Key | OpenAI or Anthropic API key |

---

## Installation

### From GitHub (manual)

1. Download the latest release ZIP from [Releases](https://github.com/your-username/wp-aigent/releases).
2. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Activate the plugin.

### Auto-Updates

Set the `Update URI` plugin header to your GitHub repository URL:

```
Update URI: https://github.com/your-username/wp-aigent
```

The built-in GitHub updater will check for new releases automatically.

---

## Quick Start

### 1. Create a Chatbot

Go to **AIgent → AI Chatbots** and click **Add New Chatbot**.

### 2. Configure API

In the **API Provider** tab:

1. **Platform** — Select OpenAI or Anthropic.
2. **API Base URL** — Defaults to `https://api.openai.com/v1` or `https://api.anthropic.com/v1`. Change for custom endpoints (OpenRouter, DeepSeek, Azure, etc.).
3. **API Key** — Enter your API key (encrypted on save).
4. **Model** — Select from auto-fetched models or enter a custom name.
5. *(Optional)* **Fallback Model** — Automatic retry if the primary model fails.

### 3. Set System Prompt

In the **System Prompt** tab:

- **① Background Info** — Company/product background the AI uses to answer visitors.
- **② AI Behavior Rules** — Security rules preventing prompt injection.
- **③ Lead Collection Items** — Define what visitor information the AI should collect.

### 4. Add Knowledge (Optional)

1. Go to **Knowledge Base** and create documents in Markdown.
2. In the chatbot **Knowledge** tab, check the documents you want the AI to reference.

### 5. Publish & Embed

- Add the **AI Chatbot** Elementor widget to a page and select your chatbot.
- Use **Box** mode to render the chat panel inline.
- Use **Button** mode to render a clickable button and position it with Elementor's Advanced positioning controls.

---

## Configuration Reference

### Elementor Widget Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Greeting Message | First message sent to the visitor (Markdown supported) | `Hello! How can I help you today?` |
| Offline Message | Message shown when offline | `We are currently offline. Please leave a message.` |
| Thinking Text | Optional text shown beside typing dots | *(empty)* |
| Layout Mode | `Box` (embedded) or `Button` (popup trigger) | `Button` |
| Colors | Popup/Header and Button colors (independent) | `#25b366` |
| Button Icon | Elementor icon library selector | Envelope icon |
| Send Icon | Elementor icon library selector | Paper plane icon |
| Close Icon | Elementor icon library selector | Times icon |
| Button Hint | Optional tooltip next to the button | Off, text defaults to `Contact Us` |
| Auto-Open | Open popup automatically on page load | Off |
| Open Delay | Delay in seconds before auto-open | 20s |
| Cache TTL | How long to remember closed state | 1440 min (24h) |
| Popup Transition | Fade-in/out duration (0–1000ms) | 100ms |

### AI Providers

| Setting | Description | Default |
|---------|-------------|---------|
| Provider Type | `openai` (compatible) or `anthropic` | `openai` |
| API Base URL | API endpoint | `https://api.openai.com/v1` |
| API Key | Encrypted with AES-256-CBC | — |
| Model List | Fetched once per reusable provider | — |

Each chatbot configures an independent primary Provider/model pair and an optional fallback Provider/model pair.

### Chatbot AI Model

| Setting | Description | Default |
|---------|-------------|---------|
| Primary AI Provider | Provider connection for the primary request | — |
| Primary Model | Model for chat completions | — |
| Fallback AI Provider | Provider connection used after a primary failure | *(disabled)* |
| Fallback Model | Model used with the fallback provider | *(disabled)* |
| Input Tokens | Max context window (reference only) | 128000 |
| Output Tokens | Max response tokens | 4096 |
| Temperature | Response randomness (0–2) | 0.2 *(disabled by default)* |
| Extended Thinking | Reasoning effort for supported models | Off |

### Memory

| Setting | Description | Default |
|---------|-------------|---------|
| Max History Rounds | Past conversation rounds sent to AI | 10 |
| Session TTL | Inactivity timeout in hours | 168 (7 days) |

### Lead Capture

| Setting | Description | Default |
|---------|-------------|---------|
| Enable | Show contact form when rules match | On |
| Form Fields | Custom input fields | name, email, WhatsApp |
| Trigger Rules | OR/AND grouped conditions | `lead_score = D` |

### Notifications

| Setting | Description | Default |
|---------|-------------|---------|
| Enable | Send notifications | Off |
| Email | Recipient email address | — |
| WeCom Webhook | Webhook URL for 企业微信 | — |
| Notification Rules | OR/AND grouped conditions | `lead_score` changed to A, B, or C |
| Inactivity Timeout | Defer evaluation until idle for N hours | Off |

### Lead Score Reference

| Score | Meaning |
|-------|---------|
| **A** | Complete lead: project is clear + at least one contact method |
| **B** | Interested lead: contact method + clear interest, partial details |
| **C** | Contact only: contact method but no meaningful project details |
| **D** | Details only: project requirements but no contact method |
| **E** | General inquiry: no contact, no clear requirements |

---

## REST API

公开接口不使用 `v1`、`v2` 等 URL 版本段。Visitor 签名只存在于 Host-only、HttpOnly Cookie，不得通过参数、Header、响应或 localStorage 传递。前端请求必须携带浏览器凭证；可信子域名还需由部署方配置明确的凭据 CORS。

### POST `/ai-chat/visitor`

校验现有 Visitor Cookie，必要时签发新身份，并在剩余有效期不超过 15 天时续签。响应只包含公开的 `visitor_id` 和凭证到期时间。

### POST `/ai-chat/chat`

发送 Chatbot 消息。Visitor ID 由服务端从 Cookie 取得。

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `chatbot_id` | int | 是 | 已发布 Chatbot 的 ID |
| `message` | string | 是 | 消息文本，长度受 Security 设置限制 |
| `metadata` | object | 否 | Page URL、referrer 和 language |

### POST `/ai-chat/history`

加载当前有效 Conversation 的历史，但不创建空 Conversation。

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `chatbot_id` | int | 是 | 已发布 Chatbot 的 ID |

Chat缺少有效Cookie时会在签发限流通过后由服务端创建新身份并继续当前消息；History缺少有效Cookie时返回HTTP 401和`visitor_credential_required`。请求中的旧无签名Visitor ID和`visitor_token`会被忽略且不能认领历史身份；`/ai-chat/v1/...` 路由不再受支持。

---

## Hooks & Filters

### Actions

| Hook | Description |
|------|-------------|
| `plugins_loaded` | Defines session and encryption constants (priority 1) |

### Filters

| Filter | Description |
|--------|-------------|
| `ai_chatbot_knowledge_context` | Modify the knowledge base context injected into the AI prompt |

---

## Development

### Building from Source

No build step is required — the plugin uses vanilla JavaScript and CSS.

To contribute:

1. Clone the repository.
2. Create a feature branch from `main`.
3. Make changes to the PHP, JS, or CSS files directly.
4. Test with WordPress 6.7+ and PHP 8.0+.

---

## License

GPL v2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
