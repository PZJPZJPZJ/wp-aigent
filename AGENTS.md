# WP AIgent — 项目指南

> 面向各类开发工具的项目说明文档，可被 `CLAUDE.md`、`.cursor/rules/`、`copilot-instructions.md` 等文件引用。

## 项目概览

WP AIgent 是一个 WordPress 插件，提供一体化 AI 工具集：多平台 AI 聊天机器人（OpenAI / Anthropic）、知识库问答、线索收集、通知（邮件 / 企业微信）以及 AI 增强的 Elementor 表单。

**运行要求**：WordPress 6.7+、PHP 8.0+；渲染聊天组件时需要 Elementor。

---

## 架构

### 目录结构

```
wp-aigent.php                          # 插件入口、常量与自动加载
includes/
├── class-plugin.php                   # WP_AIGent_Plugin：启动、钩子、资源注册
├── class-installer.php                # WP_AIGent_Installer：启用与停用流程
├── class-github-updater.php           # WP_Plugin_Github_Updater：GitHub Release 自动更新
├── ai-chatbot/
│   ├── class-cpt-provider.php          # "ai_provider" CPT：可复用 AI Provider 连接配置
│   ├── class-cpt-chatbot.php           # "ai_chatbot" CPT、元数据、默认配置
│   ├── class-cpt-knowledge.php         # "ai_knowledge" CPT（Markdown 知识文档）
│   ├── class-cpt-conversation.php      # "ai_conversation" CPT（只读、系统管理）
│   ├── class-chat-api.php              # REST：POST /ai-chat/v1/chat、GET /ai-chat/v1/history
│   ├── class-ai-client.php             # OpenAI / Anthropic 客户端与 fallback 模型支持
│   ├── class-knowledge-loader.php      # 知识上下文注入
│   ├── class-memory-manager.php        # 对话历史存储（postmeta，500 轮时清理）
│   ├── class-lead-processor.php        # 解析结构化 JSON AI 回复
│   ├── class-notifier.php              # 邮件、企业微信、规则引擎与 WP Cron
│   ├── class-widget.php                # Elementor Widget 注册入口
│   ├── class-widget-base.php           # Elementor \Widget_Base：60+ 控件和实时预览
│   ├── class-admin-ajax.php            # AJAX：预览聊天、获取模型、触发通知
│   ├── class-admin-columns.php         # 后台列表自定义列
│   └── class-export.php                # 对话导出（Markdown / JSON）
├── ai-form/
│   ├── class-ai-form.php               # 设置页与脚本加载
│   ├── class-elementor-form-enhancer.php # Elementor 国家区号字段
│   ├── class-elementor-country-code-field.php # 国家区号 <select> 渲染
│   └── class-country-resolver.php      # Cloudflare CF-IPCountry 国家识别与 250 国家数据
assets/
├── ai-chatbot/
│   ├── css/chat-widget.css             # 前端聊天组件样式
│   ├── css/admin.css                   # 管理后台样式
│   ├── js/chat-widget.js               # 前端聊天组件（原生 ES6，无框架）
│   ├── js/admin.js                     # 聊天机器人后台 UI
│   └── js/provider-admin.js            # AI Provider 后台 UI
├── ai-form/
│   ├── css/ai-form-admin.css           # AI 表单后台样式
│   ├── js/country-code.js              # 基于 Cloudflare 的访客国家识别
│   └── js/elementor-form-tracking.js   # 表单成功提交时推送 dataLayer
templates/
└── ai-chatbot/
    ├── admin-provider-meta-box.php     # AI Provider 连接配置 UI
    ├── admin-chatbot-meta-box.php      # 聊天机器人 6 Tab 配置 UI
    ├── admin-conversation-meta-box.php # 对话详情查看器
    ├── admin-knowledge-meta-box.php    # 知识文档 Markdown 编辑器
    ├── notify-email-html.php            # HTML 邮件模板
    ├── notify-wecom-markdown.php        # 企业微信 Webhook 模板
    └── defaults/                        # 默认配置（背景、规则、JSON Schema）
```

### 聊天 API 数据流

```
chat-widget.js（fetch）
  → POST /ai-chat/v1/chat
  → AI_Chatbot_Chat_API::handle_chat()
    → 校验会话（localStorage visitor UUID + HMAC）
    → 限流（transient：每个 IP / 会话每分钟 30 次）
    → 获取或创建对话记录
    → 校验可配置的会话 TTL
    → 加载知识上下文（来自已选 ai_knowledge）
    → 加载最近 N 轮对话、摘要与现有线索
    → 组装 system prompt（背景、规则、JSON Schema、知识、摘要、线索）
    → 分别解析主 Provider / 模型与可选 fallback Provider / 模型
    → AI_Chatbot_AI_Client::chat()
      → OpenAI-compatible API / Anthropic API（支持 fallback 模型）
    → AI_Chatbot_Lead_Processor::parse()：解析 JSON
    → AI_Chatbot_Memory_Manager::append()：保存对话
    → AI_Chatbot_Notifier::notify()：评估规则、发送邮件 / 企业微信通知
    → 保存线索数据和摘要
    → 返回 { reply, session_token, lead_score, should_collect_contact }
```

### 关键设计决策

- **无构建步骤**：使用原生 JavaScript 和 CSS，直接编辑源文件。
- **访客会话**：使用 localStorage UUID 与 HMAC 签名的 session token，不要求登录。
- **AI 回复格式**：通过 system prompt 要求 AI 返回结构化 JSON；`Lead_Processor` 负责解析，并回退支持 Markdown 代码块中的 JSON。
- **Provider 所有权**：平台、API URL、API Key 与已获取模型列表属于 `ai_provider`；Chatbot 与未来功能通过 Provider ID 复用连接。
- **线索与通知规则**：采用分组 OR / AND 规则，规则数组存储在 postmeta。
- **API Key 加密**：Provider API Key 使用 WordPress salts 的 AES-256-CBC 加密存储。
- **每个 Chatbot 的配置**：存储在 `ai_chatbot` CPT 的 postmeta，不使用 options 表。
- **Elementor 依赖**：Widget 渲染依赖 Elementor；未启用时显示后台提示。

### REST API

| 方法 | 端点 | 说明 |
| --- | --- | --- |
| POST | `/ai-chat/v1/chat` | 向 Chatbot 发送消息 |
| GET | `/ai-chat/v1/history` | 加载对话历史 |

### AI 平台

| 平台 | API 格式 | 特性 |
| --- | --- | --- |
| OpenAI-compatible | `/chat/completions` | `reasoning_effort`、自动获取模型列表 |
| Anthropic | `/messages` | Extended Thinking、adaptive mode |
| Fallback | 次级模型 | 主模型失败时自动重试 |

### 钩子

| Hook | 类型 | 说明 |
| --- | --- | --- |
| `ai_chatbot_knowledge_context` | Filter | 修改注入 AI prompt 的知识上下文 |
| `wp_aigent_phone_countries` | Filter | 修改国家区号数据集 |
| `ai_chatbot_inactivity_notify` | Action | WP Cron：检查闲置会话通知 |

---

## 核心类

| 类 | 文件 | 职责 |
| --- | --- | --- |
| `WP_AIGent_Plugin` | `includes/class-plugin.php` | 启动、模块加载、资源和钩子注册 |
| `WP_AIGent_Installer` | `includes/class-installer.php` | 插件启用 / 停用流程 |
| `WP_Plugin_Github_Updater` | `includes/class-github-updater.php` | GitHub Release 更新器 |
| `AI_Chatbot_CPT_Provider` | `includes/ai-chatbot/class-cpt-provider.php` | 可复用 Provider 连接、加密凭据与模型列表 |
| `AI_Chatbot_CPT_Chatbot` | `includes/ai-chatbot/class-cpt-chatbot.php` | Chatbot CPT、元数据和默认值 |
| `AI_Chatbot_Chat_API` | `includes/ai-chatbot/class-chat-api.php` | REST API 与聊天编排 |
| `AI_Chatbot_AI_Client` | `includes/ai-chatbot/class-ai-client.php` | 多平台模型调用与 fallback |
| `AI_Chatbot_Notifier` | `includes/ai-chatbot/class-notifier.php` | 规则引擎、邮件、企业微信和 WP Cron |
| `AI_Chatbot_Widget_Base` | `includes/ai-chatbot/class-widget-base.php` | Elementor Widget、60+ 控件与实时预览 |
| `WP_AIGent_Country_Resolver` | `includes/ai-form/class-country-resolver.php` | 国家识别与 250 国家区号数据 |

---

## 开发规范

### 无构建步骤

项目使用原生 JavaScript 和 CSS，不使用 npm、webpack 或其他构建流水线。

### 创建 Release

推送符合 `v*` 的 tag，会触发 `.github/workflows/release.yml`，创建带自动生成说明的 GitHub Release。

### 国际化

Text domain 为 `wp-aigent`。所有面向用户的字符串必须使用 WordPress i18n 函数，例如 `__()`、`esc_html__()`。

### 语言偏好

- 所有项目文档必须使用中文。
- 源代码、标识符和代码注释必须使用英文。
- WordPress i18n 函数中的用户可见文本不受“源代码文本使用英文”的限制，应根据产品需求本地化。

### 编码约定

- 遵循 WordPress PHP 约定：函数和变量使用 snake_case。
- 类名前缀：`WP_AIGent_`、`AI_Chatbot_`、`WP_Plugin_Github_`。
- 不使用 PHP namespace，采用类名前缀隔离。
- 当前未配置正式的 linter 或 style checker。

### 项目结构与设计

- 按大型可维护项目组织目录：模块职责清晰，相关代码放在同一边界内。
- 保持高内聚、低耦合。模块通过窄且明确的接口协作，不直接依赖其他模块的内部状态或存储细节。
- 新功能必须考虑未来扩展：将可复用基础设施与功能编排分离，避免一次性的硬编码依赖；仅在确有价值时提供扩展点。
- 数据所有权必须明确。跨功能复用的能力（例如 Provider 连接）应放入专属模块，不能在多个消费者中复制。

### UI 设计

- UI 设计不得使用最大宽度限制，应充分利用当前屏幕和 WordPress 内容区的可用宽度。
