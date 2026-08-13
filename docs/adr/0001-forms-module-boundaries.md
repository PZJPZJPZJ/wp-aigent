# ADR 0001：Forms 分析模块边界

## 状态

已接受。

## 决策

Elementor 表单分析按目标架构拆分为 Forms 业务模块、Elementor Integration、Admin Presentation、模块资源和模板。Forms 拥有分析配置、任务编排、本地标准化规则、结果数据与 Schema 升级；Elementor Integration 只负责协议转换和第三方数据只读访问，并通过 `WP_AIGent_Form_Submission_Source` 契约注入业务用例。

数据库继续使用 `wp_aigent_form_analysis_schema_version`、`{$wpdb->prefix}aigent_form_analyses` 与 `{$wpdb->prefix}aigent_form_analysis_jobs`，避免无业务收益的数据迁移。当前复用既有 Provider Query 与 AI Client；待共享 AI Gateway 落地时，Forms Service 只替换该依赖，不改变 Elementor Adapter、Repository 或后台接口。

## 约束

- Forms 业务代码不得读取 Elementor 数据表或接收 Elementor 对象。
- Elementor Adapter 不写第三方表，不修改 Submission 状态。
- Admin Controller 只做权限、输入输出和 Application Service 调用。
- 模板不查询数据库。
- Schema 升级可重复执行并由 Forms 独立 Schema Version 控制。
- 只有脱敏后的需求文本允许进入 AI 调用。
