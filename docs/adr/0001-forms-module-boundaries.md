# ADR 0001：Forms 分析模块边界

## 状态

已接受。

## 决策

Elementor 表单分析按目标架构拆分为 Forms 业务模块、Elementor Integration、Admin Presentation、模块资源和模板。Forms 拥有分析配置、任务编排、本地标准化规则、结果数据与 Schema 升级；Elementor Integration 只负责协议转换和第三方数据只读访问，并通过 `WP_AIGent_Form_Submission_Source` 契约注入业务用例。

数据库使用 `wp_aigent_form_analysis_schema_version`、`{$wpdb->prefix}aigent_form_analyses`、`{$wpdb->prefix}aigent_form_analysis_jobs` 与固定筛选快照表 `{$wpdb->prefix}aigent_form_analysis_job_items`。分析表只以 Elementor `submission_id` 关联并保存 AI 结果与运行元数据，不复制第三方原始字段。当前复用既有 Provider Query 与 AI Client；待共享 AI Gateway 落地时，Forms Service 只替换该依赖，不改变 Elementor Adapter、Repository 或后台接口。

## 约束

- Forms 业务代码不得读取 Elementor 数据表或接收 Elementor 对象。
- Elementor Adapter 不写第三方表，不修改 Submission 状态。
- Admin Controller 只做权限、输入输出和 Application Service 调用。
- 模板不查询数据库。
- Schema 升级可重复执行并由 Forms 独立 Schema Version 控制。
- 只有经过本地敏感信息模糊化的 Submission 副本允许进入 AI 调用，原始联系方式不得发送。
- 分析结果不区分结果版本，每个来源记录只保留一个当前结果；“更新分析”跳过成功记录，“覆盖分析”重新分析并覆盖当前结果。
- Elementor Submission 是列表唯一数据源；跨来源筛选由 Forms Query 通过只读 Source Contract 和分析 Repository 协调，不允许 Forms SQL 引用 Elementor 表名。
- 每个 Job 必须固化全部筛选命中的 Submission ID，并且同一时间仅允许一个活动 Job。
