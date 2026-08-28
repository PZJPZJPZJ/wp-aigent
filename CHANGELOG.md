# Changelog

本文记录 WP AIgent 的重要变更与重大技术决定，格式遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循项目的“主版本.发布版本.测试版本”规则。

## [Unreleased]

## [2.1.0] - 2026-08-28

### Added

- Lead Attribution设置新增可配置dataLayer事件名，默认`elementor_form`，限制为80字符及字母、数字、下划线、点和短横线；非法或空值回退默认值。
- Settings直接显示GTM Custom Event Trigger应监听的名称，并说明GA4 Event Tag建议发送`generate_lead`。

### Changed

- Elementor `submit_success`监听覆盖所有Elementor Form并可独立于Attribution Tracking启用；Tracking关闭时不加载Browser State依赖、不写localStorage且不注册原生submit监听器。
- 每个成功Form始终向dataLayer推送event、form_id和page_path；存在合法`wp_aigent_attribution` JSON时额外加入lead_event_id，Hidden缺失、损坏或没有event_id时仍推基础Payload。
- **重大决定：插件接管现有Elementor成功追踪代码。** 实现继续使用Elementor官方`submit_success` jQuery事件并只push dataLayer，不直接请求GA4。部署时保留现有GTM `elementor_form` Trigger并删除Elementor Custom Code、主题或GTM Custom HTML中的旧监听代码；两套Handler同时存在会重复统计。回滚到`2.0.13`时恢复旧代码或把GTM Trigger改回插件旧事件名`elementor_generate_lead`。

### Security

- dataLayer成功处理不读取用户输入、不修改验证或AJAX、不发起网络请求；Journey、source、Referrer和Visitor ID不会进入成功事件Payload。

## [2.0.13] - 2026-08-28

### Changed

- Elementor `wp_aigent_attribution` Hidden值从多行文本改为紧凑Journey JSON；每项强制path/time，source/referrer_url仅在检测到值时出现。
- source/referrer改为逐页计算：UTM、Google click ID或站外Referrer产生字段，后续站内导航或直接刷新省略可选键；普通页面去重同时比较path、source和referrer_url。
- `form_submit`与UUID event_id在捕获到原生submit时立即写回localStorage并进入Hidden JSON，明确表示提交尝试；验证失败、AJAX失败或网络失败仍会保留，dataLayer的`submit_success`才表示确认成功。
- Chat与Conversation保留经过校验的Form事件；无效event_id只移除事件字段，不影响该Journey项目的path/time。
- Conversation Attribution在Lead Data之后解析为动态列表格；Time/Path固定显示，Source、Referrer、Event和Event ID仅在对应列存在任意值时显示。
- Journey limit最小值从1调整为2，以同时保留第一项来源记录和最新Form事件；2.0.9至2.0.12旧归因继续只读兼容。

### Security

- Hidden JSON、localStorage和Chat attribution继续不包含Visitor ID或schema；完整query风险与Excluded Path要求保持不变。

## [2.0.12] - 2026-08-28

### Fixed

- Chat Button弹窗的默认宽高改为随视口连续收缩，并在窄屏默认保留32px横向空间和160px纵向空间，避免固定断点下聊天框突然变成近乎全屏并顶到页面顶部；Elementor自定义响应式宽高保持可用。

## [2.0.11] - 2026-08-28

### Added

- Elementor AI Chatbot组件新增响应式聊天宽度和高度设置，可分别为桌面、平板和手机选择尺寸与单位；留空时保持现有Button弹窗和内嵌Box默认尺寸。

## [2.0.10] - 2026-08-27

### Changed

- Attribution改为无`schema_version`的Journey-only结构；localStorage只保存`{version:3,preferences}`，移除根Visitor ID和identity作用域，同时保留Chatbot UI偏好。
- 第一条Journey记录`path`、`time`、`source`和`referrer_url`，后续页面只记录`path`和`time`；path与Referrer保留完整query并排除hash。
- Elementor Hidden值从JSON改为每行一条的可读文本；Form提交副本末尾增加`event=form_submit`和`event_id`，事件不写回长期localStorage。
- Form不再关联WP AIgent Visitor。Chat继续通过HttpOnly签名Cookie取得可信Visitor ID，并由Conversation自身字段保存，不依赖localStorage。
- Conversation Attribution从独立Meta Box移动到主详情的Lead Data之后，不重复显示Visitor ID；2.0.9 First/Last Touch数据保持只读兼容展示，新Journey到达后覆盖旧投影。
- Journey limit最小值调整为1，第一条来源记录始终保留；数量或12KiB超限时从第二条开始裁剪最旧记录。

### Security

- **重大决定：按业务要求保留完整query。** 该选择能够保留所有URL参数，但可能把邮箱、Token、订单号、搜索文本或其他敏感数据写入localStorage、Form、Email或CRM。插件不自动脱敏参数；管理员必须通过Excluded Path Prefixes排除敏感页面，并确保站点不在URL中放置秘密。回滚到`2.0.9`会恢复白名单参数和本地公开Visitor ID结构，回滚前后均需清理页面与CDN缓存。
- Chat Journey仍经过服务端形状、字段、日期、URL、数量和12KiB校验；无效归因只被丢弃，不影响AI请求，也不进入Prompt、通知、错误日志或公共REST响应。

## [2.0.9] - 2026-08-27

### Added

- 新增默认关闭的Lead Attribution设置，支持90天滚动保留、Journey数量、排除路径、可选dataLayer成功事件和现有国家区号开关。
- 新增共享`wp_aigent_browser_state`前端资产；Chat与Attribution复用同一状态实现，并在Chat确认身份后保存公开Visitor ID的确认时间和到期时间。
- 新增First/Last Touch、UTM、GCLID/WBRAID/GBRAID、外部Referrer和pathname Journey采集；归因只在Elementor专用Hidden字段提交或Chat消息metadata中发送。
- 新增服务端Attribution白名单校验、12KiB上限、Conversation当前归因投影和只读后台展示。

### Changed

- **重大决定：以单监听器浏览器归因取代Form AI分析。** 背景是旧能力依赖Elementor Pro Submission表、三张自有分析表、后台批处理Job和模型调用，运行边界较重且不能形成标准Interaction。最终方案默认关闭归因，每页只同步更新一次统一localStorage，并仅注册一个捕获阶段submit监听器；管理员必须预先添加非必填`wp_aigent_attribution` Hidden字段。未采用自动创建字段、MutationObserver、focus/pointer监听、Form侧Visitor网络请求或Elementor服务端Record修改，以保证脚本、存储或配置异常时原提交不阻塞。
- Form侧Visitor ID改为可选关联值：只读取Chat已经确认且公开到期时间未结束的localStorage UUID，不为Form请求身份，不参与授权、计费、反欺诈或Customer自动合并。
- Chat在现有`metadata.attribution`中附加快照；服务端始终以Cookie身份覆盖客户端Visitor ID，无效归因只被丢弃，不影响AI回复。
- 国家区号字段的Elementor注册、Cloudflare检测、默认国家降级和服务端格式化保持不变，启用值在新设置保存前兼容读取旧`elementor_enabled`。
- 旧Form分析表`aigent_form_analyses`、`aigent_form_analysis_jobs`、`aigent_form_analysis_job_items`、Schema Version和旧option原样保留但停止读取、写入和升级，以支持回滚；部署后需清理WordPress、Nginx和Cloudflare缓存，回滚时恢复`2.0.8`插件文件并再次清理缓存即可重新读取旧数据。

### Removed

- 移除AI Forms和Submissions后台入口、Form Analysis Schema安装、Job、Repository、Normalizer、AI Service、Elementor Submission Adapter、AJAX Controller及专属后台资源。
- 插件不再读取Elementor Submission表，也不再调用模型分析Form Submission。

### Security

- 归因禁止采集完整query、hash、页面正文、表单输入、PII、IP、User Agent、Cookie签名或Token；表单和dataLayer中的前端字段始终视为不可信营销数据。
- Chat归因经过服务端schema、类型、日期、path、URL、Journey和大小校验，不进入AI Prompt、Knowledge、通知正文、错误日志或公共REST响应。

## [2.0.8] - 2026-08-26

### Changed

- Chat在缺少、过期或签名无效的Visitor Cookie时，先执行每IP签发限流，再由服务端生成全新UUID和HttpOnly Cookie，并继续处理当前消息；无需客户端先完成独立身份请求。
- 为兼容仍被页面缓存、CDN或浏览器加载的旧Widget，Chat和History忽略请求中的废弃`visitor_id`、`visitor_token`及Token Header。这些值不能认领旧身份，服务端始终以有效Cookie或新签发身份为准。
- 前端所有技术错误只写入浏览器控制台；聊天框只显示Chatbot配置的Offline Message，未配置时不添加错误气泡。

### Fixed

- 修复新版PHP与缓存旧JavaScript混跑时，用户看到“Visitor credentials must be supplied by the server-owned cookie”并无法继续聊天的问题。
- 修复端点缺失、HTTP/API错误和网络异常时向访客直接显示内部错误文本，以及身份初始化失败后仍显示正常Greeting的问题。

### Security

- 兼容旧请求时只丢弃客户端身份字段，不恢复客户端UUID授权；旧UUID仍不能读取历史或关联原Conversation。

## [2.0.7] - 2026-08-26

### Changed

- Security 设置中的客户端 IP 读取方式改为面向部署场景的选择框：源服务器读取 `REMOTE_ADDR`；服务器反代在可信代理边界内从右向左解析 `X-Forwarded-For`，并在缺失时回退 `X-Real-IP`；Cloudflare 代理只在连接来自 Cloudflare 官方网络或额外可信 CIDR 时读取 `CF-Connecting-IP`。
- Cloudflare 模式内置当前官方 IPv4 与 IPv6 代理网段；额外 Trusted proxy CIDRs 仅用于中间 Nginx、负载均衡或 Tunnel。若 Web Server 已把真实 IP 恢复到 `REMOTE_ADDR`，仍使用源服务器模式。
- 旧 `client_ip_source` 设置自动映射到新的 `client_ip_mode`，源服务器映射为 `origin_server`，原代理 Header 模式映射为 `reverse_proxy`。

### Security

- 三种模式均在可信代理边界外回退 `REMOTE_ADDR`，避免客户端直接伪造 `X-Forwarded-For`、`X-Real-IP` 或 `CF-Connecting-IP` 绕过 Visitor 签发与公共接口限流。

## [2.0.6] - 2026-08-26

### Added

- 新增全局 Security 设置，分别配置 Visitor 签发、Chat、History、消息长度、客户端 IP 来源和可信代理 CIDR。
- 新增 Chat Application Service，公共 REST 与管理员预览在完成各自权限校验后复用同一聊天编排。

### Changed

- Visitor ID 改为服务端通过 `random_bytes()` 生成的 UUID v4，并使用 90 天滚动有效的 Host-only、HttpOnly、SameSite=Lax 签名 Cookie 证明持有权；HTTPS 同时使用 Secure 与 `__Host-` 前缀，剩余 15 天内访问时续签同一 Visitor ID。
- REST 入口改为无版本 URL：`POST /ai-chat/visitor`、`POST /ai-chat/chat`、`POST /ai-chat/history`。History 从 GET 改为 POST，Chat 与 History 仅从 Cookie 取得可信 Visitor ID。
- 浏览器状态升级为 version 2，只保留公开 `visitor_id` 和 UI preferences；所有前端请求使用 `credentials: include`，为部署方配置的可信子域名 CORS 保留能力。
- Chat 和 History 限流拆分为独立的 IP 桶与 Visitor 桶；代理 Header 只有在 `REMOTE_ADDR` 命中可信代理 CIDR 时才会解析。
- **重大决定：服务端拥有 Visitor 身份。** 背景是旧版仅校验客户端 UUID 格式，攻击者可冒用身份写入消息或读取历史。最终采用无状态 HttpOnly HMAC Cookie，不建立 Visitor Session 表，也不使用 localStorage Bearer Token 或父域共享 Cookie。该决定不迁移或删除旧 Conversation，但旧无签名身份不能认领旧历史；部署必须清理页面/CDN 缓存，回滚会重新暴露原漏洞且不能恢复旧浏览器关联。
- **重大决定：统一变更与决策记录。** 项目删除 `docs/` 与独立 ADR 机制，今后的所有修改和重大决定统一写入本文件。选择 Changelog 是为了让变更、版本、发布说明和 Commit Message 使用同一事实来源；旧 ADR 的有效决定迁入本文件，回滚时不得重新创建 `docs/adr/`。

### Removed

- 移除旧 `/ai-chat/v1/...` 路由、GET History、请求中的 `visitor_id`/`visitor_token` 凭证和 localStorage Visitor Token，不提供兼容转发。
- 移除 `docs/adr/` 及独立 ADR 文件。
- Chat 与 History 公共响应不再返回内部 `conversation_id`。

### Fixed

- 修复客户端可自选 Visitor ID 导致的身份冒用、跨 Visitor 消息写入和聊天历史越权读取。
- 修复直接信任 `X-Forwarded-For`、轮换 Visitor ID 可绕过组合限流的问题。

### Security

- Visitor 签名不再进入 URL、JSON、Header、localStorage、日志或 Prompt；篡改、过期或 Salt 变化后的 Cookie 无法访问旧身份。
- Visitor、Chat 和 History 响应使用 private/no-store 缓存策略，公开访客 WordPress REST nonce 不再被误用为匿名身份凭证。

## [2.0.5] - 2026-08-13

### Added

- 新增 Elementor Submission 手动分析、筛选快照 Job、本地敏感信息模糊化、需求总结、垃圾邮件和意图分析。

### Changed

- **重大决定：Forms 分析模块边界。** Forms 拥有分析配置、任务编排、本地标准化、结果表和独立 Schema Version；Elementor Integration 只通过 `WP_AIGent_Form_Submission_Source` 契约提供只读 DTO，不向业务层传递第三方对象，也不写 Elementor 数据。分析结果只以 `submission_id` 关联，不复制原始字段；每个 Job 固化全部筛选命中的 ID，同一时间只允许一个活动 Job。替代方案中的直接跨模块 SQL、修改 Elementor Submission 或复制完整原始记录均被拒绝，以保持来源只读、隐私边界和后续 AI Gateway 可替换性。
