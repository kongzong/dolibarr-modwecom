# modWeCom — Dolibarr 企业微信集成模块

Dolibarr 22.0.x 外部模块，集成企业微信（WeCom）。**V0.1 功能已全部实现**，零 core 修改。

## 功能总览

| 功能 | 入口 | 权限 |
|---|---|---|
| 连接配置（Corp ID / Agent ID / Secret / Token / EncodingAESKey） | 设置 → WeCom | wecom admin |
| 测试连接 / 强制刷新 Token | 设置页按钮 | wecom admin |
| 部门同步（→ 用户组） | 员工同步页 | wecom sync |
| 员工同步（→ 用户 + 映射） | 员工同步页 | wecom sync |
| 外部联系人同步（→ ThirdParty + Contact + 映射） | 客户同步页 | wecom sync |
| 客户详情页"企业微信"Tab（信息 / 解绑 / 发消息） | 第三方公司卡片 | societe lire + wecom read |
| Webhook 回调（签名验证 / AES 解密 / 幂等） | `/custom/wecom/wecom/webhook.php` | 无需登录（签名保证安全） |
| 企业微信 OAuth 扫码登录 | 登录页"企业微信登录"按钮 | 已映射用户 |
| REST API | `/api/index.php/wecom/...` | 按端点区分 |

## 安装

1. `wecom` 目录放到 `htdocs/custom/wecom/`（DoliWamp: `D:\dolibarr\www\dolibarr\htdocs\custom\`）
2. Dolibarr → 设置 → 模块/应用 → 搜索 WeCom → 启用（自动建 5 张 `llx_wecom_*` 表、注册权限/菜单/Tab/Hook）
3. 给用户授予相应权限（用户 → 权限 → 模块 WeCom）
4. 设置页填入企业微信参数并"测试连接"

## 企业微信侧配置清单

- 自建应用（Agent ID / Secret）
- 应用可见范围：覆盖全部员工（部门同步、员工同步）
- 客户联系 → API → 可调用接口的应用：添加本应用（外部联系人同步）
- 企业可信 IP：服务器出口 IP（客户联系 API 强制要求）
- 接收消息（Webhook）与 OAuth 登录：回调 URL 域名需**ICP 备案且主体一致**（本地开发用 `tests/integration/simulate_webhook.php` 模拟验证）

## REST API

所有端点需 `DOLAPIKEY`（用户卡片生成 API key）：

```
GET  /api/index.php/wecom/status                  # read：模块/Token/同步状态
POST /api/index.php/wecom/sync/users              # sync
POST /api/index.php/wecom/sync/external-contacts  # sync
GET  /api/index.php/wecom/users                   # read：用户映射
GET  /api/index.php/wecom/contacts                # read：客户映射
GET  /api/index.php/wecom/contacts/{rowid}        # read
POST /api/index.php/wecom/messages                # message：{content, wecom_userid|fk_soc}
GET  /api/index.php/wecom/events                  # read：Webhook 事件
```

AI Agent 只需 Dolibarr API key，永远接触不到企业微信 Secret。

## 测试

```
# 单元测试（无需 PHPUnit，内置兼容运行器）
php tests/run_all.php

# Webhook 集成模拟（本机完整链路：验签/解密/幂等/重放）
php tests/integration/simulate_webhook.php
# 或在装有 PHPUnit 的环境：phpunit --testsuite unit tests/unit/
```

## 卸载

禁用模块只移除常量/权限/菜单/Tab，不删 Dolibarr 业务数据。彻底清理需手动删除 `llx_wecom_*` 表。

## 已知限制（V0.1）

- Webhook 与 OAuth 的真实公网联调需备案域名（企业微信硬性要求），逻辑层已本地验证
- 应用消息仅支持 text（按规格 V0.1 范围），只发给内部员工（负责销售）
- 系统 Trigger 通知（客户/合同创建时自动推送）留待 V0.2

## 阶段规划

详见开发规格：V0.2（更多事件/标签/Markdown 消息/Trigger 通知）→ V0.3（钉钉/飞书抽象）→ V0.4（Agent Skill/MCP）。

## 开发约定

本模块遵循 [custom/DOLIBARR-MODULE-DEVELOPMENT.md](../DOLIBARR-MODULE-DEVELOPMENT.md) 中的最佳实践与 AI 协作红线；本机环境、模块状态与交接信息见 [custom/AGENTS.md](../AGENTS.md)。
