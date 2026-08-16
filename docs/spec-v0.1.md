# Dolibarr 企业微信集成模块（modWeCom）V0.1 开发规格

## 1. 文档目的

本项目以 Dolibarr 作为业务 Runtime，开发第一个面向中国市场的独立模块 `modWeCom`，用于验证：

1. Dolibarr Module 是否可以作为标准业务 Plugin 开发。
2. 一个 Module 是否可以同时提供后端、前端、数据模型、权限、Webhook、外部系统集成。
3. Module 是否可以通过标准 REST API 被外部 AI Agent 使用。
4. 后续其他中国生态模块是否可以复用同一开发模式。

本模块不是“企业微信全功能客户端”，也不是聊天系统。

V0.1 的目标是形成一个最小、完整、可运行、可被 AI Coding Agent 维护的参考级 Dolibarr Module。

---

# 2. 总体原则

## 2.1 不修改 Dolibarr Core

所有功能必须通过 Dolibarr Module 机制实现：

- Module Descriptor
- SQL / Migration
- Permission
- Menu
- Page
- Hook
- Trigger
- API
- Language
- CSS / JS
- Cron（V0.1 暂不需要）

Dolibarr 官方 ModuleBuilder 已支持生成模块描述器、API、单元测试、SQL、对象页面、权限、Hook、Trigger、CSS/JS 等模块骨架，因此实现时优先使用官方 ModuleBuilder 生成结构，再补充业务代码。

参考：
- Dolibarr Module Development: https://wiki.dolibarr.org/index.php?title=Module_development
- ModuleBuilder: https://wiki.dolibarr.org/index.php/Module_ModuleBuilder

---

## 2.2 不重新实现 Dolibarr 已有能力

以下能力直接使用 Dolibarr：

- 用户
- 权限
- Session
- 第三方公司（ThirdParty / Societe）
- 联系人（Contact）
- 菜单
- 页面布局
- 翻译
- 日志基础能力
- REST API 基础设施
- Hook / Trigger
- 数据库连接

`modWeCom` 只负责企业微信相关业务。

---

## 2.3 第一版只做企业微信，不抽象成万能工作平台

V0.1 不同时实现：

- 钉钉
- 飞书
- 个人微信
- 微信支付
- 企业微信客户群
- 微信聊天记录
- AI 自动回复

未来可以基于 V0.1 抽象公共接口，但现在禁止过度设计。

---

# 3. V0.1 功能范围

## 3.1 P0 功能

必须实现：

1. 企业微信应用配置
2. 企业微信 API Access Token 管理
3. 企业微信管理员配置页面
4. 企业微信员工同步
5. 企业微信部门同步
6. 员工与 Dolibarr User 映射
7. 企业微信外部联系人同步
8. 外部联系人与 Dolibarr ThirdParty / Contact 映射
9. Webhook 回调
10. Webhook 签名验证
11. Webhook 事件日志
12. Dolibarr → 企业微信应用消息
13. 企业微信 OAuth 登录
14. 模块权限
15. 模块后台管理页面
16. REST API
17. 安装 / 卸载 / 初始化
18. 单元测试和集成测试
19. 中文/英文语言文件

## 3.2 V0.1 明确不做

- 微信聊天记录同步
- 客户朋友圈
- 企业微信客户群
- 群机器人
- AI 自动回复
- 微信支付
- 企业微信审批
- 企业微信营销
- 个人微信
- 钉钉
- 飞书
- 自动创建复杂 CRM 商机流程
- 自动改写 Dolibarr Customer 核心行为
- 财务/发票

---

# 4. 模块定位

模块名称：

```text
modWeCom
```

目录：

```text
htdocs/custom/wecom/
```

建议结构：

```text
wecom/
├── core/
│   └── modules/
│       └── modWeCom.class.php
├── class/
│   ├── wecomapi.class.php
│   ├── wecomconfig.class.php
│   ├── wecomusermap.class.php
│   ├── wecomcontactmap.class.php
│   └── wecomeventlog.class.php
├── admin/
│   ├── setup.php
│   ├── users.php
│   ├── contacts.php
│   ├── mappings.php
│   ├── events.php
│   └── test_connection.php
├── wecom/
│   ├── oauth.php
│   ├── callback.php
│   └── webhook.php
├── api/
│   ├── index.php
│   └── routes/
├── lib/
│   └── wecom.lib.php
├── sql/
│   ├── llx_wecom_config.sql
│   ├── llx_wecom_user_map.sql
│   ├── llx_wecom_contact_map.sql
│   └── llx_wecom_event_log.sql
├── langs/
│   ├── zh_CN/
│   │   └── wecom.lang
│   └── en_US/
│       └── wecom.lang
├── css/
│   └── wecom.css
├── js/
│   └── wecom.js
├── tests/
│   ├── unit/
│   └── integration/
├── docs/
│   └── README.md
└── README.md
```

实际目录名称应以当前 Dolibarr 版本的外部模块规范为准。

---

# 5. 模块功能模型

```text
modWeCom
│
├── Configuration
│
├── Authentication
│   └── Enterprise WeChat OAuth
│
├── Organization Sync
│   ├── Departments
│   └── Users
│
├── Customer Sync
│   └── External Contacts
│
├── Mapping
│   ├── User Mapping
│   └── Contact Mapping
│
├── Webhook
│   └── Event Receiver
│
├── Notification
│   └── Application Message
│
├── REST API
│
└── Admin UI
```

---

# 6. 企业微信配置

## 6.1 配置项

后台页面：

```text
首页
└── 设置
    └── 模块/应用
        └── 企业微信
```

必须提供：

```text
Corp ID
Agent ID
Secret
Token
EncodingAESKey
```

建议增加：

```text
Webhook URL
OAuth Callback URL
API 状态
Access Token 状态
最后同步时间
```

Secret / Token / EncodingAESKey：

- 不允许写入日志
- 页面默认掩码
- 不允许通过普通 GET API 返回
- 数据库存储时按现有 Dolibarr 安全方式处理
- 错误日志中禁止输出

---

# 7. 企业微信 API Client

统一封装：

```php
WeComApi
```

禁止业务代码直接：

```php
curl(...)
```

业务代码只能调用：

```php
$wecomApi->getAccessToken();
$wecomApi->getDepartments();
$wecomApi->getUsers();
$wecomApi->getExternalContacts(...);
$wecomApi->sendMessage(...);
```

API Client 负责：

- Access Token 获取
- Access Token 缓存
- Token 过期刷新
- HTTP 请求
- 超时
- 重试
- 错误转换
- 企业微信错误码记录
- JSON 编解码

---

# 8. Access Token

原则：

```text
企业微信 Access Token
↓
模块缓存
↓
过期自动刷新
```

不要每次业务请求都重新获取 Token。

Access Token 缓存至少包含：

```text
token
expires_at
updated_at
```

建议在 Dolibarr 配置表或独立缓存机制中保存。

第一版不引入 Redis。

---

# 9. 企业微信员工同步

## 9.1 同步方向

```text
企业微信
   ↓
Dolibarr
```

V0.1 不允许从 Dolibarr 反向修改企业微信组织架构。

---

## 9.2 同步对象

Department：

```text
wecom_department_id
name
parent_id
order
```

User：

```text
wecom_userid
name
mobile
email
position
department
status
avatar
```

---

# 10. Dolibarr User 映射

新增：

```text
llx_wecom_user_map
```

建议字段：

```text
rowid
fk_user
wecom_userid
wecom_unionid
wecom_openid
wecom_department_ids
date_creation
tms
status
```

唯一约束：

```text
wecom_userid UNIQUE
fk_user UNIQUE
```

映射原则：

```text
WeCom User
      ↓
wecom_user_map
      ↓
Dolibarr User
```

禁止在 Dolibarr `llx_user` 中加入大量企业微信专用字段，除非后续证明 Core 修改具有长期价值。

---

# 11. 部门映射

V0.1 使用 Dolibarr 用户组承载企业微信部门。

```text
WeCom Department
       ↓
Dolibarr User Group
```

映射规则：

```text
wecom department id
        ↓
Dolibarr group id
```

同一部门必须幂等。

删除部门时：

- 默认不删除 Dolibarr Group
- 标记映射失效
- 避免误删 Dolibarr 原有用户权限

---

# 12. 企业微信 OAuth 登录

流程：

```text
浏览器
  ↓
Dolibarr Login
  ↓
WeCom OAuth
  ↓
WeCom UserId
  ↓
wecom_user_map
  ↓
Dolibarr User
  ↓
Dolibarr Session
```

V0.1 只支持企业内部成员。

未映射用户：

```text
登录失败
```

并提示：

```text
“企业微信账号尚未关联 Dolibarr 用户，请联系管理员。”
```

不要自动创建 Dolibarr 用户。

---

# 13. 外部联系人同步

这是模块的核心业务功能。

企业微信：

```text
External Contact
```

映射至 Dolibarr：

```text
ThirdParty
Contact
```

建议映射：

```text
企业微信 External Contact
            │
            ▼
       wecom_contact_map
            │
       ┌────┴────┐
       ▼         ▼
ThirdParty    Contact
```

---

# 14. 外部联系人映射表

新增：

```text
llx_wecom_contact_map
```

字段：

```text
rowid
external_userid
fk_soc
fk_contact
wecom_type
wecom_state
wecom_name
wecom_avatar
wecom_corp_name
owner_wecom_userid
date_creation
tms
status
```

唯一约束：

```text
external_userid UNIQUE
```

---

# 15. 客户匹配策略

V0.1 不做复杂 AI 匹配。

优先级：

```text
1. 已存在映射
2. 管理员指定绑定
3. 企业微信企业客户名称 + 手工确认
4. 无法匹配 → 创建新 ThirdParty
```

禁止：

```text
自动模糊匹配后直接合并客户
```

因为 CRM 主数据错误代价很高。

---

# 16. 外部联系人同步数据

至少同步：

```text
external_userid
name
avatar
type
corp_name
position
external_profile
follow_user
state
```

其中：

```text
follow_user
```

用于确定：

```text
哪个销售负责这个企业微信客户
```

---

# 17. 客户详情页扩展

不修改 Dolibarr Customer 原页面源码。

通过 Hook 增加：

```text
企业微信
```

Tab 或信息区域。

示例：

```text
客户
├── 卡片
├── 联系人
├── 商业行为
├── 文档
└── 企业微信
```

企业微信 Tab 展示：

```text
企业微信客户
客户名称
External User ID
客户类型
企业名称
负责销售
最后同步时间
同步状态
```

并提供：

```text
重新同步
解除绑定
发送消息
```

Dolibarr 的 Hook 机制本身就是用于在不直接修改核心代码的情况下扩展已有页面和行为；Trigger 则用于响应业务事件，两者职责应严格区分。

参考：
https://wiki.dolibarr.org/index.php/Hooks_system
https://wiki.dolibarr.org/index.php/Triggers_system

---

# 18. Webhook

Webhook Endpoint：

```text
/custom/wecom/wecom/callback.php
```

实际实现必须遵循当前企业微信官方接口要求。

支持：

```text
GET  验证 URL
POST 接收事件
```

处理流程：

```text
HTTP Request
   ↓
Token 验证
   ↓
AES 解密
   ↓
解析 Event
   ↓
Event Log
   ↓
幂等检查
   ↓
业务处理
   ↓
快速返回
```

绝对不能在 Webhook 请求中执行长时间同步任务。

---

# 19. Webhook Event Log

新增：

```text
llx_wecom_event_log
```

字段：

```text
rowid
event_id
event_type
event_time
payload_hash
payload
process_status
process_message
retry_count
date_creation
tms
```

唯一：

```text
event_id
```

如果企业微信事件没有稳定 Event ID，则根据：

```text
event_type + event_time + business_id + payload_hash
```

生成幂等 Key。

敏感信息必须脱敏。

---

# 20. Webhook 支持事件

V0.1 只做对 CRM 有价值的事件。

### 员工

```text
create_user
update_user
delete_user
```

### 部门

```text
create_department
update_department
delete_department
```

### 外部联系人

```text
change_external_contact
```

后续再扩展更多事件。

---

# 21. Event Processing

Webhook 接收到事件后：

```text
Webhook
 ↓
wecom_event_log
 ↓
业务 Dispatcher
 ↓
Customer/User Mapping
```

处理必须幂等。

重复事件：

```text
第一次 → Process
第二次 → Ignore
```

不能造成重复客户。

---

# 22. 应用消息

只实现发送企业微信应用消息。

抽象：

```php
$wecomApi->sendApplicationMessage(
    $userid,
    $message
);
```

第一版支持：

```text
text
```

可选支持：

```text
markdown
```

不做复杂卡片消息。

---

# 23. 消息场景

第一版只提供两个业务入口：

### 手工发送

Customer 企业微信 Tab：

```text
发送消息
```

### 系统通知

通过 Dolibarr Trigger：

```text
客户创建
合同创建
```

允许后续 Plugin 调用：

```php
WeComNotifier
```

但 V0.1 不建立复杂通知规则系统。

---

# 24. REST API

API 是本模块的重要输出，因为 AI Agent 不进入 Dolibarr 内部运行，而是通过 API 使用 Dolibarr。

V0.1 API：

```text
GET  /api/index.php/wecom/status
POST /api/index.php/wecom/sync/users
POST /api/index.php/wecom/sync/external-contacts
GET  /api/index.php/wecom/users
GET  /api/index.php/wecom/contacts
GET  /api/index.php/wecom/contacts/{id}
GET  /api/index.php/wecom/mappings/users
GET  /api/index.php/wecom/mappings/contacts
POST /api/index.php/wecom/messages
GET  /api/index.php/wecom/events
```

实际 URL 以目标 Dolibarr 版本 REST API 路由机制为准。

---

# 25. AI Agent API 原则

AI Agent 不应该直接调用：

```text
WeCom API
```

而调用：

```text
Dolibarr API
```

例如：

```text
Agent
  ↓
GET /api/index.php/thirdparties
  ↓
Dolibarr
```

或者：

```text
Agent
  ↓
GET /api/index.php/wecom/contacts
  ↓
modWeCom
  ↓
Dolibarr + 企业微信
```

Agent 不需要知道企业微信 Token。

---

# 26. API 安全

AI Agent 使用 Dolibarr API：

```text
API Key / Token
```

权限由 Dolibarr 统一控制。

企业微信 Secret：

```text
绝不暴露给 Agent
```

Agent 只能访问：

```text
Allowed Dolibarr API
```

---

# 27. Admin UI

模块必须提供管理页面：

```text
企业微信
├── 概览
├── 连接配置
├── 员工同步
├── 客户同步
├── 用户映射
├── 客户映射
├── Webhook 日志
└── API/状态
```

---

# 28. 概览页面

显示：

```text
连接状态
企业微信 Corp ID
应用状态
员工数量
已映射员工
外部联系人数
已绑定客户数
最后员工同步
最后客户同步
Webhook 最近状态
```

不要显示 Secret。

---

# 29. 同步页面

员工：

```text
同步员工
```

客户：

```text
同步外部联系人
```

显示：

```text
新增
更新
跳过
失败
```

提供同步日志。

---

# 30. 权限

V0.1 至少：

```text
wecom.read
wecom.write
wecom.admin
wecom.sync
wecom.message
```

建议：

```text
read    查看
write   修改映射
admin   修改配置
sync    执行同步
message 发送消息
```

---

# 31. 数据权限

模块本身不重新建立完整 ACL。

使用：

```text
Dolibarr User Permission
```

企业微信 Customer 映射必须遵循 Dolibarr Customer 的既有权限。

例如：

```text
用户不能查看 Customer
→
不能查看对应企业微信信息
```

不能通过：

```text
/wecom/contacts
```

绕过 Dolibarr Customer 权限。

---

# 32. 中文本地化

至少：

```text
zh_CN
en_US
```

所有 UI 文本进入 lang 文件。

禁止在 PHP 中硬编码中文。

例如：

```php
$langs->trans("WeCom");
$langs->trans("SyncUsers");
$langs->trans("ExternalContacts");
```

---

# 33. 错误处理

所有企业微信错误统一转成内部异常：

```text
WeComApiException
```

包含：

```text
error_code
error_message
request_id
http_status
```

日志必须：

- 记录错误码
- 记录接口名称
- 记录时间
- 记录重试次数
- 不记录 Secret
- 不完整记录 Access Token

---

# 34. HTTP 重试

默认：

```text
连接失败 → 重试
5xx → 重试
明确业务错误 → 不重试
认证错误 → 刷新 Token 后重试一次
```

第一版不要实现复杂指数退避，只需：

```text
1 次快速重试
```

---

# 35. 幂等

必须保证：

### 用户同步

同一个：

```text
wecom_userid
```

只能对应一个 Dolibarr User Mapping。

### 联系人同步

同一个：

```text
external_userid
```

只能对应一个 Mapping。

### Webhook

重复事件不能重复创建 Customer。

---

# 36. 日志

日志分类：

```text
Connection
Sync
Webhook
Message
OAuth
```

管理员可以查看：

```text
时间
类型
结果
错误码
摘要
```

敏感信息必须脱敏。

---

# 37. 数据库原则

所有表：

```text
llx_wecom_*
```

使用 Dolibarr 数据库前缀。

不要硬编码：

```text
llx_
```

使用：

```php
$conf->db->prefix
```

或当前 Dolibarr 推荐方式。

字段类型必须兼容 Dolibarr 支持的数据库。

---

# 38. 安装流程

启用模块：

```text
Setup
→ Modules
→ WeCom
→ Enable
```

第一次启用：

```text
创建模块表
注册权限
注册菜单
注册 API
注册 Hook
注册 Trigger
```

安装失败必须：

```text
事务回滚 / 可恢复
```

---

# 39. 卸载原则

禁止默认删除：

```text
Dolibarr Customer
Dolibarr User
Dolibarr Contact
```

卸载模块只处理：

```text
wecom tables
module config
module mappings
module logs
```

卸载前提示：

```text
解除企业微信映射不会删除 Dolibarr 客户数据。
```

---

# 40. 测试要求

## 40.1 单元测试

测试：

```text
Access Token
API Client
User Mapping
Contact Mapping
Webhook Signature
Event Idempotency
Message Builder
```

---

## 40.2 集成测试

至少：

```text
安装模块
启用模块
创建配置
同步员工
同步外部联系人
创建映射
Webhook
发送消息
REST API
卸载模块
```

---

# 41. Mock

测试环境不能依赖真实企业微信。

实现：

```text
MockWeComApi
```

Mock：

```text
getAccessToken()
getDepartments()
getUsers()
getExternalContacts()
sendApplicationMessage()
```

Webhook 测试数据使用固定 Fixture。

---

# 42. Acceptance Test

以下全部通过才算 V0.1 完成。

### A. 安装

```text
全新 Dolibarr
↓
安装 modWeCom
↓
安装成功
```

### B. 配置

```text
填写 Corp ID / Agent ID / Secret
↓
测试连接
↓
成功
```

### C. 用户同步

```text
企业微信员工
↓
同步
↓
Dolibarr User
```

重复同步不会产生重复 User。

### D. 客户同步

```text
企业微信 External Contact
↓
同步
↓
Dolibarr ThirdParty / Contact
```

重复同步不会产生重复 Customer。

### E. 页面扩展

打开 Customer：

```text
Customer
→ 企业微信 Tab
```

可以看到映射信息。

### F. Webhook

```text
WeCom Event
↓
Webhook
↓
Event Log
↓
Mapping Update
```

重复事件只处理一次。

### G. 消息

```text
Customer
↓
发送企业微信消息
↓
API 成功
```

### H. API

Agent 使用 Dolibarr API：

```text
查询企业微信客户
↓
正常返回
```

### I. 权限

无：

```text
wecom.read
```

不能读取企业微信信息。

无：

```text
wecom.admin
```

不能修改配置。

---

# 43. AI Coding 开发方式

开发过程中必须优先遵循：

1. 当前目标 Dolibarr 版本的源码。
2. 官方开发文档。
3. ModuleBuilder 生成的代码。
4. 本规格。
5. 不猜测 Dolibarr API。

AI Coding Agent 开始工作前必须先：

```text
读取：
- README
- 当前 Dolibarr 版本
- 官方 ModuleBuilder 模板
- 当前模块开发文档
```

然后再编码。

---

# 44. AI Coding Agent 的第一任务

第一阶段不写业务代码。

先完成：

```text
Step 1
创建 modWeCom 骨架

Step 2
能够在 Dolibarr 中 Enable

Step 3
创建数据库表

Step 4
注册一个权限

Step 5
注册一个菜单

Step 6
显示一个 Admin 页面

Step 7
提供一个测试 API

Step 8
提供 PHPUnit 测试
```

这 8 项通过后，再开发企业微信功能。

---

# 45. 第二阶段任务

```text
WeComApi
Access Token
Configuration
Test Connection
```

完成后：

```text
管理员
↓
配置企业微信
↓
点击测试
↓
看到成功/失败
```

---

# 46. 第三阶段任务

```text
Department Sync
User Sync
User Mapping
```

完成：

```text
企业微信组织架构
↓
Dolibarr User / Groups
```

---

# 47. 第四阶段任务

```text
External Contact Sync
Contact Mapping
Customer Hook / Tab
```

完成：

```text
企业微信客户
↓
Dolibarr Customer
↓
Customer Detail
↓
企业微信信息
```

---

# 48. 第五阶段任务

```text
Webhook
Event Log
Idempotency
```

完成实时同步。

---

# 49. 第六阶段任务

```text
Application Message
REST API
Permission
```

完成 Agent 可调用能力。

---

# 50. 代码质量要求

必须：

- 遵循目标 Dolibarr 版本代码规范。
- 使用 Dolibarr 提供的数据库 API / 类。
- 使用 Dolibarr 权限系统。
- 使用 Dolibarr Translation 系统。
- 使用 Dolibarr Hook / Trigger。
- 不修改核心文件。
- 不复制 Dolibarr 核心代码。
- 不引入大型第三方 Framework。
- 所有外部 HTTP 请求经过 `WeComApi`。
- 所有数据库操作经过模块自己的类/DAO。
- 所有外部输入必须验证。
- 所有企业微信数据必须做长度和类型校验。

---

# 51. AI Coding 禁止事项

AI 不得：

```text
1. 修改 htdocs/core 下核心业务文件。
2. 修改 Dolibarr 核心 Customer / User 类。
3. 直接调用 curl 分散在多个业务文件中。
4. 把企业微信 Secret 写入源码。
5. 把 Secret 写入 Git。
6. 把 Access Token 输出到日志。
7. 绕过 Dolibarr Permission。
8. 自动模糊合并客户。
9. 在 Webhook 请求中做长耗时同步。
10. 为不存在的 Dolibarr API 自行猜接口。
```

---

# 52. 完成定义

V0.1 不是“代码写完”。

必须同时满足：

```text
✓ 能安装
✓ 能启用
✓ 能配置
✓ 能连接企业微信
✓ 能同步员工
✓ 能同步外部联系人
✓ 能映射 Dolibarr Customer
✓ 能展示 Customer 企业微信 Tab
✓ 能接 Webhook
✓ Webhook 幂等
✓ 能发送应用消息
✓ 有 REST API
✓ 有权限控制
✓ 有中文
✓ 有单元测试
✓ 有集成测试
✓ 能卸载
✓ 不修改 Dolibarr Core
```

---

# 53. V0.1 最终结构

```text
                Enterprise WeChat
                       │
          ┌────────────┼────────────┐
          ↓            ↓            ↓
      OAuth         Webhook       API
          │            │            │
          └────────────┼────────────┘
                       ↓
                   modWeCom
                       │
       ┌───────────────┼────────────────┐
       ↓               ↓                ↓
  User Mapping   Contact Mapping   Event Log
       │               │                │
       ↓               ↓                ↓
  Dolibarr User    Customer/Contact   Trigger
                       │
                       ↓
                Dolibarr Runtime
                       │
               REST API / Agent
                       │
                       ↓
                    AI Agent
```

---

# 54. 后续扩展边界

V0.1 完成后，再考虑：

```text
V0.2
├── 更多企业微信事件
├── 客户标签
├── 客户负责人同步
├── 应用消息 Markdown
└── 更完整的 OAuth

V0.3
├── 钉钉
├── 飞书
└── 统一企业协作平台接口

V0.4
├── Agent Skill
├── MCP
└── AI 自动化业务流程
```

这些功能必须建立在 V0.1 已验证的 Dolibarr Module + REST API 基础上，不反向污染 `modWeCom` 的核心设计。

---

# 55. 第一条开发任务

AI Coding Agent 收到本文件后，首先执行：

```text
1. 检查当前 Dolibarr 版本。
2. 阅读该版本官方 Module Development 文档。
3. 阅读 ModuleBuilder 生成模板。
4. 创建 modWeCom 外部模块骨架。
5. 不实现企业微信 API。
6. 先完成“安装 → 启用 → 菜单 → 管理页面 → 权限 → 测试 API → 测试”的最小闭环。
7. 输出实现计划和变更文件列表。
8. 然后再开始编码。
```

第一阶段禁止一次性生成整个模块。

原因：先验证模块目录、Module Descriptor、权限、菜单、页面、API、数据库和测试是否符合目标 Dolibarr 版本，再继续业务开发。

---

## 官方参考

Dolibarr Module Development：
https://wiki.dolibarr.org/index.php?title=Module_development

Dolibarr ModuleBuilder：
https://wiki.dolibarr.org/index.php/Module_ModuleBuilder

Dolibarr Developer Documentation：
https://wiki.dolibarr.org/index.php?title=Developer_documentation

Dolibarr Hooks：
https://wiki.dolibarr.org/index.php/Hooks_system

Dolibarr Triggers：
https://wiki.dolibarr.org/index.php/Triggers_system
