# WP AIgent — 项目指南

> 本文同时定义当前实现、目标架构和后续开发约束，是开发工具与贡献者的统一项目说明。规划中的模块会明确标注实现状态，禁止把规划能力误认为已经可用。

## 项目定位

WP AIgent 是一个面向 WordPress 的 AI 客情管理插件。当前提供多平台 AI Provider、Elementor 聊天机器人、知识库问答、对话与线索记录、邮件/企业微信通知、Elementor 国家区号字段，以及按筛选结果手动分析 Elementor Submission 的能力。

长期目标不是堆叠相互独立的 AI 工具，而是以 Visitor、Interaction 和 Customer 为主线，将 Chat、Form 及未来邮件、商城、CRM 等渠道统一转换为客户互动，通过确定性规则与 AI 提取可追溯事实，形成标准 Customer Profile，再驱动生命周期、统计、跟进和自动化。

**运行要求**：WordPress 6.7+、PHP 8.0+。聊天组件和 Elementor Forms Integration 依赖 Elementor；Submission 分析依赖 Elementor Pro 的 Submission 数据表。

## 实现状态说明

- **已实现**：已有可运行代码和后台入口，可以作为现有能力使用。
- **部分实现**：已有可运行能力，但数据模型、异步化或模块边界尚未达到本文目标。
- **未实现**：仅定义未来边界和约束，不存在可用产品能力。

## 统一目录布局

项目只使用以下最终目录布局。禁止重新创建 `ai-chatbot/`、`ai-form/`、`migrations/` 或其他过渡目录。

```text
wp-aigent.php                         # 最小插件入口：常量与生命周期 Hook
includes/
├── bootstrap/                       # [已实现] 装配与启用/停用流程
│   ├── class-bootstrap.php
│   └── class-installer.php
├── core/                            # 不依赖具体业务模块
│   ├── ai/                          # [部分实现] AI Client、Token Usage；目标为共享 AI Gateway
│   ├── contracts/                   # [部分实现] 当前包含 Form Submission Source 契约
│   ├── identity/                    # [部分实现] 全局 Visitor ID 与后台签发凭证
│   ├── jobs/                        # [未实现] Job、重试、锁与执行器
│   ├── database/                    # [未实现] 通用事务、分页与数据库能力
│   ├── http/                        # [未实现] REST 响应、权限、限流与校验基础设施
│   ├── security/                    # [部分实现] 当前拥有全局 Security 设置
│   ├── attribution/                 # [未实现] 标准 UTM、referrer、click ID 模型
│   ├── events/                      # [未实现] 领域事件分发与事件名称约束
│   └── support/                     # [未实现] 少量真正通用的值对象与纯函数
├── modules/
│   ├── providers/                   # [已实现] Provider CPT、加密凭据和模型目录
│   ├── chatbots/                    # [部分实现] Chatbot CPT、Prompt 和同步 Chat REST
│   ├── knowledge/                   # [已实现] 文档 CPT、索引、路由和检索
│   ├── conversations/               # [部分实现] Conversation CPT、消息和摘要
│   ├── forms/                       # [部分实现] 配置、国家数据、手动 Submission 分析
│   ├── intelligence/                # [部分实现] 当前仅有 Chat 结构化 Lead 解析
│   ├── notifications/               # [部分实现] 规则、邮件、企业微信和闲置 Cron
│   ├── interactions/                # [未实现] 所有渠道的标准 Interaction 入口
│   ├── customers/                   # [未实现] Customer Profile、Identity Link、合并拆分
│   ├── lifecycle/                   # [未实现] Lead 阶段、负责人、标签和跟进
│   └── analytics/                   # [未实现] 聚合读模型、漏斗、趋势和 AI 用量统计
├── integrations/
│   ├── elementor/                   # [已实现] Widget、Forms 字段、Submission 只读 Adapter
│   ├── wordpress/                   # [部分实现] 当前包含 GitHub 更新器
│   ├── channels/                    # [未实现] Email、WeCom 等独立通知渠道 Adapter
│   └── marketing/                   # [未实现] Google Ads、Meta、CRM 等 Adapter
└── admin/
    ├── chatbots/                    # [已实现] Assets、AJAX 和列表列
    ├── conversations/               # [已实现] 对话导出
    ├── forms/                       # [已实现] Forms 设置、分析页面与 AJAX
    ├── menu/                        # [未实现] AIgent 菜单和顺序的统一所有者
    ├── rest/                        # [未实现] 统一后台 REST Controller
    └── shared/                      # [未实现] 后台共用表格、筛选和状态组件
assets/
└── modules/<module>/                # 模块独占的原生 CSS / JavaScript
templates/
└── modules/<module>/                # 模块模板和默认配置；不得查询数据库
tests/                               # [未实现] unit / integration / contract
CHANGELOG.md                         # 变更日志、重大决定、兼容与回滚记录
```

不存在代码的规划目录不应为了“看起来完整”而提前创建。目录应在出现真实实现时建立，禁止空目录、单纯转发类和无业务价值的层级。

## 模块状态与数据所有权

| 模块 | 状态 | 当前能力 | 目标所有权与后续缺口 |
| --- | --- | --- | --- |
| Providers | 已实现 | Provider CPT、协议、URL、加密 API Key、模型列表 | 继续作为连接唯一所有者；消费者不得读取 Provider postmeta |
| Chatbots | 部分实现 | Chatbot 配置、Prompt、同步聊天 API、Lead JSON | 应只拥有聊天体验与 Prompt 策略，并发布标准 Chat Interaction |
| Knowledge | 已实现 | Markdown 文档、Card、Chunk 索引、候选路由与检索 | 后续补齐引用、索引 Job、失败恢复与版本化 |
| Conversations | 部分实现 | Conversation CPT、Visitor 关联、消息、摘要、Token Usage | 高频消息应迁往独立表，并向 Interactions 发布标准互动 |
| Forms | 部分实现 | 国家区号字段、只读 Submission 扫描、本地标准化、手动需求总结 | 应产生 Form Interaction，不建立独立客户画像；分析任务应迁入通用 Jobs |
| Intelligence | 部分实现 | 解析 Chat 模型的结构化 JSON | 应拥有 Schema、Fact、Evidence、置信度、提取版本和 Profile 投影 |
| Notifications | 部分实现 | 分组规则、Email、WeCom、闲置 Cron | 应消费领域事件；渠道协议迁至 `integrations/channels`，投递记录可重试 |
| Interactions | 未实现 | 无 | 拥有所有渠道标准互动、原始快照、来源映射和幂等 Intake |
| Customers | 未实现 | 无 | 拥有 Customer Profile、Identity Link、合并、拆分和人工确认值 |
| Lifecycle | 未实现 | 无 | 拥有 Lead 阶段、有效性、负责人、标签、跟进和业务状态 |
| Analytics | 未实现 | 无 | 拥有聚合结果、缓存、漏斗、趋势和报表读模型 |

每张表、option 和 postmeta 必须有唯一所属模块，只有所属模块可以写入。跨模块读取只能通过公开 Service、Query 或 Contract；禁止直接读取其他模块的内部存储。

## 当前可运行链路

### Chat 链路（已实现，仍是同步旧链路）

```text
assets/modules/chatbots/js/widget.js
  → POST /ai-chat/chat
  → AI_Chatbot_Chat_API::handle_chat()
    → HttpOnly Visitor Cookie 校验与独立 IP / Visitor 限流
    → 按 Visitor + Chatbot + Last Activity + TTL 获取或创建 Conversation
    → Knowledge Loader 加载候选知识
    → Memory Manager 加载历史、摘要和已有 Lead
    → 组装 Prompt 与 JSON Schema 指令
    → Provider / Model / Fallback 解析
    → AI_Chatbot_AI_Client::chat()
    → Lead Processor 解析结构化 JSON
    → 保存消息、Lead、摘要和 Token Usage
    → Notifier 评估规则并投递通知
```

当前缺口：尚未产生标准 Interaction；AI、通知和摘要仍可能处于前台同步请求；Conversation 与 Lead 仍使用 CPT/postmeta；尚未形成 Customer Fact 和 Customer Profile。

### Elementor Form 分析链路（已实现，部分达到目标）

```text
管理员筛选 Elementor Submission 并点击“更新分析”或“覆盖分析”
  → 分批扫描全部筛选结果并创建固定 Job Item 快照
  → Elementor Submission Source Adapter 只读加载原始记录
  → 对完整 Submission 副本执行本地敏感信息模糊化
  → Provider 模型分析需求、垃圾邮件和客户意图
  → 仅按 Elementor submission_id 保存 WP AIgent 自有分析结果
```

- 不修改 Elementor Submission、字段、状态或已读标记。
- 不通过 Elementor Hook、页面加载或 WP Cron 自动开始分析。
- Elementor `submission_id` 唯一；AIgent 不复制提交时间、来源 URL、联系方式、标准化副本或完整 LLM JSON。
- “更新分析”在创建快照时批量标记并跳过已成功记录，重试未分析、失败、待处理和过期执行记录；“覆盖分析”重新处理全部筛选结果。
- 模型可接收表单、页面、Campaign 和全部字段的脱敏副本；不得接收本地原始联系方式。
- `Submissions` 后台入口以 Elementor 为唯一列表数据源，使用 WordPress 原生列表表格，固定显示原始 ID、Email、Form、Page URL、Submission Date，并追加 Requirements、Spam、Intent、Analysis Status。
- 默认筛选最近 30 天并按 Submission Date 倒序；搜索、Form、Page URL、日期、Spam、Intent 和 Analysis Status 会共同限定列表及分析任务。
- Elementor 原记录删除后，AIgent 对应孤儿结果会在列表查询或任务创建前分批清理。
- 当前结果尚未写入标准 Interaction、Customer Fact 或 Customer Profile，这些能力未实现。

## 产品架构主线

```text
Chat / Form / Future Channel
            ↓
    Standard Interaction
            ↓
Normalization + Identity Resolution
            ↓
     Customer Intelligence
            ↓
Facts + Evidence → Customer Profile
            ↓
Lifecycle / Analytics / Notifications
```

Visitor ID 是匿名浏览器关联键，不是客户主键、登录凭证或访问控制依据。未来身份模型必须区分：

- `visitor_id`：浏览器状态中的全局 UUID，用于匿名互动关联和归因。
- `customer_id`：WP AIgent 生成的永久 Customer 主键。
- `identity_link`：Visitor、标准化邮箱、电话、WhatsApp 等与 Customer 的关联。
- `interaction_id`：聊天、表单或其他客户触点的统一记录 ID。

同一 Customer 可以关联多个 Visitor。共享设备、跨设备和联系方式复用可能造成误合并，因此自动合并必须依赖经过验证的强标识，保留规则、证据、时间、置信度和审计记录，并支持人工拆分。AI 只能建议身份关联，不能仅凭语义猜测自动合并。

## Interaction 统一入口（未实现，新增客户能力必须遵守）

Chat、Form 和未来渠道不得继续各自维护最终 Lead 数据。所有客户触点应先转换为内部 Interaction DTO：

```text
interaction_id
visitor_id
customer_id          # 可为空，身份解析后回填
channel              # chat / form / email / ecommerce / crm
interaction_type     # message / submission / status_change / note
source_type
source_id
source_record_id
chatbot_id
form_id
page_id
page_url
attribution
occurred_at_gmt
raw_payload
```

- 来源类型与来源记录 ID 必须唯一，重复 Hook、扫描和重试不能产生重复 Interaction。
- `raw_payload` 用于追溯，但敏感字段必须加密、脱敏或排除。
- 外部对象必须在 Integration Adapter 内转换为内部 DTO，业务层不能接收 Elementor Record 等第三方实例。
- 来源删除、隐私删除和数据保留必须有明确策略。

## Customer Fact 与 Profile（未实现）

客户数据必须分为三层：

```text
Raw Interaction
      ↓
Extracted Fact + Evidence
      ↓
Current Customer Profile
```

每条 Customer Fact 至少记录：

```text
customer_id
field_key
typed_value
source_type
interaction_id
evidence_excerpt
confidence
extractor_type       # deterministic / ai / human
extractor_version
schema_version
created_at_gmt
superseded_at_gmt
```

- Profile 是有效 Facts 的当前投影，不是唯一历史 JSON。
- 重新提取只能新增或替代 Fact，不能删除原始 Interaction 和旧证据。
- 人工确认值默认锁定，AI 重跑不能覆盖，只能产生冲突建议。
- 确定性字段优先于 AI：邮箱、电话、国家、日期、数字和枚举不得交给模型猜测。
- 默认优先级：人工确认 > 表单明确字段 > 聊天明确表达 > AI 推断 > 历史低置信度结果。
- 冲突必须保留候选值、证据和选择原因，禁止静默使用最后写入覆盖。

标准 Customer Profile 目标字段至少覆盖：

- Identity：name、email、phone、whatsapp、company。
- Location：country、region、city、language。
- Business：project_type、requirements_summary、budget_min/max、currency、timeline、products_of_interest。
- Qualification：validity、lead_score、intent_level、confidence、invalid_reason。
- Lifecycle：stage、owner_user_id、first_seen_at_gmt、last_seen_at_gmt、last_interaction_at_gmt。
- Attribution：first/last touch source、medium、campaign、term、content、click IDs、latest form/chatbot。

自定义字段必须声明类型、验证、可搜索性、统计维度资格、AI 填写/覆盖权限和保留策略。高频筛选与统计字段必须成为真实索引列，不能永久存放在大 JSON 中。

## 分层与依赖方向

```text
Bootstrap
   ↓
Presentation → Application → Domain / Contracts
                         ↑
              Infrastructure / Integrations
```

每个模块按实际复杂度包含以下职责，不强制创建空目录：

1. **Presentation**：Hook、REST/AJAX Controller、后台页面和模板数据准备，只做权限、输入输出与用例调用。
2. **Application**：一个明确用户用例对应一个 Service，负责流程编排和事务边界。
3. **Domain**：规则、评分、状态转换和值对象，不调用 WordPress、HTTP 或数据库。
4. **Infrastructure**：Repository、CPT/postmeta、`$wpdb`、外部 API Adapter，实现上层窄接口。

依赖约束：

- `core/` 不依赖 `modules/`、Elementor 或具体通知渠道。
- 业务模块不得 require 其他模块的 Repository、Schema、模板或内部 Helper。
- 跨模块同步调用使用公开 Service/Query；异步副作用使用领域事件或 Job。
- Integration 可以依赖 Core Contract 和模块公开接口，模块不能依赖 Elementor 类或表结构。
- Admin Controller 不包含 SQL、Prompt、统计口径或业务状态转换。
- 模板只消费准备好的 View Model，不调用 `$wpdb`、`get_posts()` 或外部 API。
- 使用显式构造函数注入；没有真实必要前不引入全局 Service Locator 或复杂 DI 容器。

## 共享 AI 内核

当前 `core/ai` 已支持 OpenAI Chat Completions、OpenAI Responses、Anthropic、Gemini、fallback、reasoning effort、Token Usage 和模型发现，但尚未形成完整 AI Gateway。

目标 AI Gateway 必须统一处理：

- Provider/模型解析以及主模型、fallback 切换。
- 超时、有限重试、错误分类、耗时和 Token Usage 标准化。
- 结构化输出解析、JSON Schema 校验和失败诊断。
- 调用用途标记，如 `chat_answer`、`knowledge_route`、`form_requirement_summary`、`interaction_extract`。
- 敏感字段允许发送清单与脱敏；API Key、密码、IP、User Agent 不得进入 Prompt 或日志。
- 统一调用记录；默认不长期保存完整 Prompt 和原始响应。

业务模块定义任务 Prompt、Schema 和结果落库方式；AI Gateway 不理解 Customer、Lifecycle、Chatbot 或 Form 业务，AI Client 不得直接更新 Customer Profile。

## Job 与领域事件（通用设施未实现）

目标客户处理链路：

```text
Interaction Created
  → Deterministic Normalizer
  → Identity Resolver
  → AI Extraction Job
  → JSON Schema Validation
  → Customer Fact Repository
  → Customer Profile Projector
  → Lifecycle Evaluator
  → Analytics Projector
  → Notification / Automation
```

- AI 调用、批量扫描、索引、通知、导入导出和统计重建默认建模为 Job。
- Job 至少记录 `pending / running / succeeded / failed / cancelled`、尝试次数、下次执行时间、错误代码和错误摘要。
- Worker 必须有并发锁、最大尝试次数、退避和超时恢复。
- WP Cron 只是默认执行器，业务 Job 不得依赖 Cron 具体 API，以便替换为 Action Scheduler、CLI 或真实队列。
- 领域事件使用过去式，如 `wp_aigent_interaction_created`、`wp_aigent_customer_identified`、`wp_aigent_customer_profile_updated`。
- 事件 Payload 只传稳定 ID 和必要上下文，不传大型对象或可变内部实例。
- 消费者失败不能回滚已成功的主业务写入。

当前 Forms 有模块自有的手动分析任务表，Notifications 有闲置 Cron，但二者尚未迁移到通用 Job 基础设施。

## 数据与 Schema 规则

- 高频持续增长数据目标上使用独立表：消息、Interaction、Customer Fact、Identity Link、Submission 映射、Job、AI Usage、通知日志和统计聚合。
- 适合 WordPress 编辑体验的低频配置与内容可以使用 CPT/postmeta：Provider、Chatbot、知识文档。
- 每个自定义表由所属模块的 Schema 类创建和升级，不设置独立 `migrations/` 目录。
- 每个模块使用独立 Schema Version；升级必须幂等并支持从任意已发布版本逐步执行。
- SQL 只能存在于 Repository、Schema 或专用 Query 类。
- 需要筛选、排序、关联和聚合的字段必须为有索引的真实列。
- JSON 只用于低频、动态、不可预先穷举的数据。
- 外部数据采用“原始快照 + 标准字段”，同时记录来源 ID 和解析版本。
- 写入必须幂等：外部来源使用来源类型 + 来源 ID 唯一键，Job 使用幂等键。
- 批处理使用游标或主键分页，禁止无限制 `OFFSET`、一次性全表加载和长事务。
- 个人信息必须定义保留、删除、导出和来源删除后的处理策略，并接入 WordPress 隐私工具或提供等价入口。

## Integration 规则

- Elementor Submission 等外部数据只能由专用 Source Adapter 读取。
- Adapter 负责依赖检测、表存在检测、版本差异和外部字段到内部 DTO 的转换。
- 除非外部系统提供稳定写入 API，否则默认只读第三方表；禁止直接修改外部插件数据库。
- 外部 Hook 回调只做校验、DTO 转换、服务调用或 Job 投递，然后尽快返回。
- 外部类名、数据库字段和异常不得越过 Adapter 边界。
- 每个 Integration 必须提供最低版本提示、功能降级行为和后台诊断信息。

## Analytics 目标（未实现）

Analytics 必须围绕 Visitor、Customer、Interaction 和 Lifecycle 的公开 Query 构建读模型，不能扫描聊天 postmeta、Elementor Submission 或动态 Fact JSON。

目标指标包括：

- Visitor、匿名 Visitor、已识别 Customer 和身份识别率。
- Chat Interaction、Form Submission、有效客户和重复互动数量。
- Visitor → Customer、Submission → 有效客户、首次互动 → 有效客户漏斗与耗时。
- Chatbot、Form、页面、UTM Campaign、广告关键词的客户数、有效率和 Lead Score 分布。
- Lifecycle 阶段、负责人、跟进状态和阶段停留时间。
- AI 覆盖率、失败率、耗时、Token Usage 和估算成本。

所有比例必须显式定义分子和分母，禁止把不同概念统称为“转化率”。Analytics 只能读取公开 Query，不得反向修改业务记录。

## API、Hook 与扩展点

当前 REST API：

| 方法 | 端点 | 状态 | 说明 |
| --- | --- | --- | --- |
| POST | `/ai-chat/visitor` | 已实现 | 校验、续签或由后台签发 HttpOnly Visitor 凭证 |
| POST | `/ai-chat/chat` | 已实现 | 使用 Visitor Cookie 发送 Chatbot 消息 |
| POST | `/ai-chat/history` | 已实现 | 使用 Visitor Cookie 加载当前会话历史 |

公开 REST URL 不包含 `v1`、`v2` 等版本段。Chat 与 History 不接受请求参数、Header 或 localStorage 中的 Visitor Token，服务端只能从已签名的 HttpOnly Cookie 取得可信 Visitor ID。

当前扩展点：

| Hook | 类型 | 状态 | 说明 |
| --- | --- | --- | --- |
| `ai_chatbot_knowledge_context` | Filter | 已实现 | 修改注入 Prompt 的知识上下文 |
| `wp_aigent_phone_countries` | Filter | 已实现 | 修改国家区号数据集 |
| `ai_chatbot_inactivity_notify` | Action | 已实现 | WP Cron 闲置会话通知检查 |

只为真实扩展需求定义新 Contract 或 Hook。至少有两个真实实现或明确近期需求时才提取可替换接口，禁止为猜测中的未来需求制造空抽象。

## 安全与隐私

- Provider API Key 使用 WordPress salts 派生密钥进行 AES-256-CBC 加密存储。
- Visitor ID 必须由后台使用密码学安全随机源签发，不得用于登录认证、后台授权或直接认定真实用户；Chat 与 History 只能信任由服务端验证的 Visitor Cookie。
- Visitor 凭证使用 Host-only、HttpOnly、SameSite=Lax Cookie；HTTPS 下必须同时使用 Secure 与 `__Host-` 前缀。凭证不得进入 URL、JSON、localStorage、日志或 Prompt。
- 浏览器持久化统一使用 version 2 的 `wp_aigent_browser_state`：`{ version, visitor_id, preferences }`。这里只保存公开 Visitor ID 和 UI 偏好，新功能只能在 `preferences` 下增加作用域，禁止新建独立 localStorage key。
- Visitor Cookie 采用 90 天滚动有效期，剩余 15 天内访问时续签同一 Visitor ID；缺失、过期、签名错误或被篡改的旧身份必须重新签发，不能访问原身份的历史对话。
- 插件不实施严格同源 Origin 校验，也不输出通配凭据 CORS；可信子域名访问必须由部署方在 WordPress、Web Server 或反向代理中配置明确 CORS，并使用带凭据请求。
- REST/AJAX 必须执行 Capability、nonce、输入校验和限流。
- 客户端 IP 读取使用 Security 设置中的部署模式：源服务器读取 `REMOTE_ADDR`；服务器反代只在可信代理 CIDR 后解析 `X-Forwarded-For`/`X-Real-IP`；Cloudflare 只在官方网络或额外可信 CIDR 后读取 `CF-Connecting-IP`。不得无条件信任客户端 Header。
- Prompt 与日志不得包含 API Key、密码、IP、User Agent 等无业务必要的敏感数据。
- Forms 模型调用可以发送完整 Submission 的脱敏副本；姓名、邮箱、电话、WhatsApp、公司等敏感值必须先在本地模糊化，原始联系方式不得发送。
- 后台显示个人信息必须受 Capability 和隐私策略控制。
- 重要任务必须有状态、错误摘要和人工重试入口，不能只依赖 PHP error log。

## 后台与 UI 规范

- UI 不设置最大宽度限制，应充分利用 WordPress 内容区。
- 所有管理员配置项必须有简短的 `description`，说明用途、推荐选择、影响和失败行为。
- 新接触产品的用户无需阅读代码即可理解配置。
- Admin 页面准备 View Model；模板不读取数据库或外部 API。
- 列表、筛选和报表通过 Query/Report 服务获取数据。
- 原生 JavaScript 和 CSS 直接维护，不引入 npm、webpack 或构建流水线。

## 编码与文档规范

- 遵循 WordPress PHP 约定；函数和变量使用 snake_case。
- 类名前缀使用 `WP_AIGent_`、`AI_Chatbot_`、`WP_Plugin_Github_`。
- 不使用 PHP namespace，以类前缀隔离。
- 源代码、标识符和代码注释使用英文。
- 所有项目文档使用中文。
- 用户可见字符串使用 WordPress i18n 函数，text domain 为 `wp-aigent`。
- 当前没有正式 linter；变更至少执行所有 PHP 文件 `php -l`、JavaScript `node --check` 和 `git diff --check`。
- 推送符合 `v*` 的 Tag 会触发 `.github/workflows/release.yml` 创建 GitHub Release。

## Changelog 与重大决定

- 项目不使用 `docs/`、`docs/adr/` 或独立 ADR 文件；禁止重新创建这些目录记录架构决定。
- 每次代码、配置、接口、数据、文档或行为修改都必须同步写入根目录 `CHANGELOG.md`，不得只依赖 Git 历史、Commit Message 或聊天记录。
- `CHANGELOG.md` 遵循 Keep a Changelog 结构，使用 `[Unreleased]` 和带 ISO 日期的版本标题，并按 `Added / Changed / Deprecated / Removed / Fixed / Security` 分类；没有内容的分类不创建。
- 未完成工作记录在 `[Unreleased]`。形成可以安装、运行并交付测试的构建时，必须先递增插件版本，再把本次条目归入同版本标题；插件头、Changelog、交付说明和后续 Tag 必须一致。
- 重大架构或产品决定必须在对应版本的 `Changed` 条目中同时记录背景、最终决定、主要替代方案、数据与兼容影响、部署或回滚要求，确保可以据此编写 Commit Message 和发布说明。
- Commit Message 应概括对应 Changelog 条目，但不能替代 Changelog；修复后续错误时新增条目，禁止改写已经发布版本的历史事实。

## 版本号规则

版本号固定使用 `主版本.发布版本.测试版本` 三段格式：

- 最小位是测试版本号：每完成一个可以安装、运行并交付测试的构建必须递增，例如 `2.0.5 → 2.0.6`。
- 中间位是发布版本号：功能完成并通过迁移、回归和发布验收后递增，同时将测试位归零，例如 `2.0.6 → 2.1.0`。
- 最大位是重大更新版本号：存在重大架构、核心数据模型或公开产品边界升级时递增，同时将后两位归零，例如 `2.9.4 → 3.0.0`。
- 任何声称“可运行”“可测试”“可发布”的交付都必须先同步更新 `wp-aigent.php` 插件头版本和 `CHANGELOG.md` 对应版本；代码、Changelog、Release Tag 和交付说明中的版本必须一致。
- 单纯文档草稿、未完成中间状态或不可运行的工作区变更不递增版本。

## 禁止的反模式

- 禁止创建 `helpers.php`、`utils.php` 或 `functions.php` 作为无边界代码垃圾桶。
- 禁止 God Class 同时注册全部 Hook、渲染页面、访问数据库和调用 AI。
- 禁止复制 Provider、Visitor ID、规则、JSON 解析、分页或通知逻辑到多个模块。
- 禁止业务模块直接读取其他模块的 postmeta、option、表或 Repository。
- 禁止把 Elementor 等外部对象传入业务层。
- 禁止在 Hook、Controller、模板或 JavaScript 中定义唯一业务规则。
- 禁止 Chat 和 Form 分别维护不可同步的最终 Lead JSON。
- 禁止 Visitor ID 直接作为 Customer ID、登录凭证或真实身份。
- 禁止 AI 结果直接覆盖唯一 Customer JSON、人工确认值或旧证据。
- 禁止静默吞掉异常；降级必须返回稳定错误并提供可见诊断。
- 禁止没有唯一键、索引、Schema Version、删除策略和隐私策略的持久化数据。
- 禁止为了目录整齐创建空目录、空接口或只有一层转发的类。

## 演进规则

- 所有新代码直接进入本文统一目录和依赖边界，不设置过渡目录或兼容转发文件。
- 旧类只有在相关业务需求触及时拆分职责，不为纯粹改名制造无收益重写。
- 新增 Chat/Form 客情能力必须优先建设 Interaction 和 Customer Intelligence，不能继续扩大来源模块自己的 Lead 存储。
- 当前 `conversation_lead_data` 是过渡数据；在标准 Interaction、Fact、Profile 和数据迁移完成前不得直接删除。
- 当新需求需要复用旧内部逻辑时，先提取窄 Service/Query/Contract，再由调用方使用，禁止跨模块引用内部存储类。
- Breaking Change、公开入口删除或历史数据迁移必须先给出迁移步骤、回滚方式和影响范围，并获得明确确认。
- 重大架构决定统一写入 `CHANGELOG.md` 对应版本的 `Changed` 条目，记录背景、决定、替代方案、数据影响、兼容影响与回滚要求。
