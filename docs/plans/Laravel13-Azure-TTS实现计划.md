# Laravel 13 + Azure TTS 实现计划

## 1. 目标和范围

为英语学习微信小程序提供文字转语音及视频生成后端。后端使用 PHP 8.4、Laravel 13，语音合成使用 Azure Speech TTS，音视频处理使用 FFmpeg。

分阶段实现：

1. 单词生成 MP3，支持音色、语速和试听。
2. 多单词异步生成，支持重复、间隔、分组循环和缓存。
3. 单词独立音频、ZIP 打包和字母拼读。
4. 单词字幕视频生成。

当前小程序的生成按钮仅保存本地待生成记录；文件导入也只是选择文件，并未解析单词。本计划中的接口和功能尚未实现。

## 2. 总体架构

```text
微信小程序
  → Laravel API：认证、验证、创建任务
  → Laravel Queue：异步处理、共享限流
  → Azure Speech：生成基础读音
  → FFmpeg：拼接音频、可选合成视频
  → 文件存储：保存结果
  → 小程序查询任务并播放或下载
```

建议沿用后端项目现有的数据库、认证和存储体系。开发初期可使用数据库队列和本地文件存储；多 Worker 或多服务器部署时，使用共享 Redis 实现 Azure 请求限流。生产文件建议存放在私有对象存储或受控文件服务中。

## 3. 实施前确认

- Laravel 后端现有用户认证、小程序登录方式和用户模型。
- 数据库、队列、缓存和文件存储方案。
- Azure Speech 资源、区域、F0 可用性和实际音色。
- 页面上暂停时间、重复间隔、单词间隔、分组间隔各自的精确定义。
- 拼读是逐字母朗读还是自然拼读；中文释义是否需要朗读。
- 单个打包是否指每词一个 MP3，再打包为 ZIP。
- 用户文件保存期限、隐私要求及视频时长限制。

## 4. Azure Speech 配置

创建 Azure Speech 资源后，将密钥和区域仅保存在服务端环境变量或密钥管理服务中：

```dotenv
AZURE_SPEECH_KEY=
AZURE_SPEECH_REGION=
AZURE_SPEECH_OUTPUT_FORMAT=audio-24khz-48kbitrate-mono-mp3
```

在 `config/services.php` 配置并通过 `config()` 读取；不要将密钥放入小程序、版本库或日志。配置缓存启用时，部署过程需正确更新配置缓存。

Azure 官方定价页面当前列出 F0 神经网络语音每月 50 万字符；实时 TTS 的 F0 限制包括每 60 秒 20 次请求、每次最多 10 分钟音频。实际额度、地区可用性、音色和计费以 Azure 控制台及官方文档为准。其他计算、存储和流量资源可能收费。F0 不负责本项目的普通字幕视频合成。

## 5. 阶段一：单词生成 MP3

### 5.1 后端能力

- 建立服务端音色白名单，维护前端业务 ID、Azure 音色名、语言和显示名称，例如美式女声、美式男声、英式女声；确认区域可用性并试听。
- 建立语速白名单，将慢速、正常、快速映射到 SSML `prosody rate`。
- 构造 SSML；对用户文本进行 XML 转义，不接受任意用户 SSML。
- 使用 Laravel HTTP Client 请求 Azure TTS REST API，设置连接和响应超时、音频输出格式及服务端密钥。
- 检查 HTTP 状态和音频结果，失败时不将错误响应保存为 MP3。
- 初期提供受认证保护的短文本试听接口，返回音频或短期文件地址。

SSML 示例：

```xml
<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="en-US">
  <voice name="en-US-JennyNeural">
    <prosody rate="-20%">apple</prosody>
  </voice>
</speak>
```

### 5.2 验收

- 输入单词可生成有效 MP3，并在微信小程序真机播放。
- 音色和语速切换生效，失败时显示安全的错误信息。
- 密钥不会出现在前端、响应或日志中。

## 6. 阶段二：异步批量任务

### 6.1 API

| 方法和路径 | 用途 |
| --- | --- |
| `GET /api/tts/voices` | 获取可用音色和语速 |
| `POST /api/generation-tasks` | 创建异步任务，返回 202 和任务 ID |
| `GET /api/generation-tasks` | 查询当前用户任务列表 |
| `GET /api/generation-tasks/{id}` | 查询任务状态和结果 |
| `GET /api/generation-files/{id}` | 鉴权后下载文件或获取短期地址 |

创建请求示例：

```json
{
  "output_mode": "word_audio",
  "words": [
    {"english": "apple", "translation": "苹果"},
    {"english": "banana", "translation": "香蕉"}
  ],
  "voice": "us_female",
  "speed": "normal",
  "repeat_count": 2,
  "repeat_interval": 1,
  "word_interval": 2,
  "group_loop_count": 1,
  "group_interval": 2
}
```

后端按白名单验证字段、单词数量和长度、重复与间隔范围，并限制组合后的最大工作量和预计输出时长。支持幂等键，避免重复点击或网络重试创建重复任务。当前小程序使用驼峰字段，提交时需统一转换。

任务状态：`pending`、`processing`、`completed`、`failed`。进度可先按处理步骤估算，不必作为精确剩余时间。

### 6.2 数据表建议

- `generation_tasks`：任务 ID、用户 ID、输出模式、状态、进度、参数快照、错误信息及时间。
- `generation_task_words`：任务 ID、顺序、英文、中文释义。
- `generation_files`：任务 ID、类型、磁盘、路径、文件名、MIME 类型、大小、时长、过期时间。
- `tts_audio_cache`：缓存键、服务商、音色、语速、格式、路径和最近使用时间。

文件和任务查询必须校验所属用户；数据库保存文件路径或标识，不依赖永久公开 URL。用户输入与缓存应遵循隐私和保留期限要求。

### 6.3 生成策略

推荐每个不同单词按音色和语速生成一次基础音频并缓存，再由 FFmpeg 按设置重复、插入静音和拼接。该方式适合后续单词打包和视频，也可减少重复文本的合成调用。缓存键需覆盖文本、语言、音色、语速、格式及其他影响声音的参数；私有文本不应未经判断跨用户共享。

实现任务处理时保证重复执行安全，使用任务锁、状态检查和受控输出路径。记录失败原因但不泄露密钥或用户敏感文本。

### 6.4 限流与错误

- 所有 Worker 在调用 Azure 前使用同一资源级共享限流器；不要依赖队列本身自动限流。
- 设置余量，429 按服务端提示或延迟策略有限重试。
- 网络超时和 5xx 有限重试；401、403 和参数错误应检查配置或输入，避免无限重试。
- 控制用户调用频率、队列并发和最大任务大小，并监控 Azure 用量、失败率和费用。

### 6.5 验收

- 多个单词按预期顺序、次数和间隔生成可播放 MP3。
- 任务状态及失败信息可查询，用户不能读取他人任务。
- 缓存命中时不重复调用 Azure；重复提交和任务重试不会产生不可控重复结果。

## 7. 阶段三：ZIP 和拼读

- 每词生成独立 MP3，使用安全文件名及序号避免同名覆盖，打包 ZIP。
- 在隔离临时目录中处理，完成或失败后清理；防止路径穿越。
- 字母拼读可评估 SSML 的字符朗读或后端逐字母拼接，需实际试听。
- 自然拼读和音素拆分需要额外的词典或发音规则，作为独立功能设计，不等同于逐字母朗读。

## 8. 阶段四：单词视频

Azure 提供音频，Laravel 调用 FFmpeg / FFprobe 将单词、中文释义、背景与读音合成为 MP4。首版建议静态画面，按实际音频时长安排每个单词。

可选画面尺寸：16:9 使用 1280×720，9:16 使用 720×1280，1:1 使用 720×720；最终按性能和产品需求调整。服务端需要可商用且支持中文的字体。

视频任务必须限制并发、时长、尺寸和执行时间。通过参数数组调用进程，不能将用户文本直接拼入 shell 命令；同时正确处理 FFmpeg 滤镜或字幕文件的转义。隔离临时文件并清理中间产物。

## 9. 小程序接入

1. 获取或同步服务端音色列表。
2. 将页面单词和设置提交到创建任务接口。
3. 保存返回的任务 ID，轮询任务状态；页面隐藏或关闭时停止不必要的轮询。
4. 展示等待、处理中、完成和失败状态。
5. 完成后使用微信音频 API 播放，或通过受控地址下载。
6. 历史记录从服务端读取，不再仅依赖本地存储。
7. 文件导入单独实现上传、解析、预览和错误提示；当前选择文件并不等于完成导入。

正式环境需配置微信小程序相关合法域名；私有文件访问需校验身份或使用短期地址。

## 10. 测试与上线检查

- 单元测试：XML 转义、音色白名单、参数组合限制、缓存键、文件名安全。
- 功能测试：创建任务、权限、幂等、成功、429 重试、401 失败和安全错误信息；使用 HTTP 模拟避免消耗 Azure 额度。
- 少量真实集成验证：Azure 网络、音色和语速、MP3 格式、FFmpeg 输出及微信真机播放。
- 配置密钥管理、用量和账单告警、队列监控、文件过期清理与备份策略；告警不等于自动停费。

## 11. 首期交付范围

先交付：用户输入多个英文单词，选择真实可用音色和语速，设置重复与间隔，提交异步任务后，在小程序播放生成的 MP3。ZIP、自然拼读和视频在音频流程稳定后分期实施。

## 参考资料

- [Azure Speech 定价](https://azure.microsoft.com/zh-cn/pricing/details/speech/)
- [Azure Speech 配额与限制](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-services-quotas-and-limits)
- [Azure TTS REST API](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-text-to-speech)
- [Azure SSML 声音参数](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup-voice)
- [Laravel 13 HTTP Client](https://laravel.com/docs/13.x/http-client)
- [Laravel 13 Queues](https://laravel.com/docs/13.x/queues)
