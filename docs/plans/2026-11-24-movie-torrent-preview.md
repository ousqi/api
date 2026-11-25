# 电影种子预览界面

> **AI 生成聲明 (AI-Generated Declaration)**：本文件由 AI 依需求分析自動生成，內容尚未經人類審核。僅供參考，需人類 review + confirm 後方可執行。
> **Review Status**: ⚪ Pending
> **Reviewed By**: (人工填寫姓名/日期)

**Human Review**: ⚪ Pending
**Created**: 2026-11-24
**Status**: 🔄 In Progress
**Priority**: High

---

## Background & Goals

使用既有的 MTeam API 建立不依賴資料庫的電影種子預覽頁。排程指令定時抓取並轉換資料至 Laravel 快取；前台僅讀取快取，提供電影海報牆、圖片失敗佔位與詳細資訊互動。

## Tasks

| # | Task | Status | Sub Agent | Notes |
|---|------|--------|-----------|-------|
| 1 | 確認既有服務、路由、排程、前端與測試慣例 | ✅ Complete | explore | MTeam 搜尋端點使用 POST，頁面可維持僅讀快取 |
| 2 | 建立快取刷新服務、Artisan 指令及每 15 分鐘排程 | ✅ Complete | general | 成功且非空時才寫入快取；排程避免重疊 |
| 3 | 建立電影海報預覽路由、控制器與 Blade 響應式介面 | ✅ Complete | general | 二級分類各自使用獨立快取，頁面按子類快取讀取與聚合 |
| 4 | 補足快取與頁面行為測試、格式化並驗證 | ✅ Complete | general | 已涵蓋單子類刷新、失敗保留既有快取、父類聚合與全分類刷新；聚焦驗證 12 tests／38 assertions、語法與差異檢查通過 |
| 5 | 依計畫進行實作審查 | ✅ Complete | code-reviewer | 獨立子類快取與刷新命令最終審查完成，無 Critical／Major／Minor findings |
| 6 | 修正 MTeam 主導航中文標籤回歸並更新功能測試 | ✅ Complete | code-reviewer | 僅調整首頁與主分類可見標籤／導覽無障礙標籤；不變更分類 ID、路由、快取或既有子分類標籤；複審無 Critical／Major findings，Minor 僅為測試精確性建議 |
| 7 | 修正獨立分類刷新審查 Minor findings | ✅ Complete | general | 補足全分類 ID 順序、排程設定測試，並修正服務例外類別匯入；聚焦驗證完成 |
| 8 | 修正電影／電視劇分類選單無法實際操作的問題 | 🔄 In Progress | general | 已修正 Escape 依事件目標所屬控制器關閉，避免行動版同時展開時錯誤關閉第一個選單；建置、既有功能測試與 host Chrome 行動版雙選單 Escape 驗證皆已完成，待獨立審查。 |
| 9 | 限制首頁各已設定二級分類的快取顯示數量，並調整快取預設期限 | 🔄 In Progress | general | 已移除服務內與設定檔衝突的 3,600 秒 TTL fallback，預設值僅由 `config/services.php` 提供（86,400 秒）；新增非預設 TTL 傳遞、首頁快取聚合不呼叫 API 與非預設每類上限的回歸測試。聚焦測試、Pint、語法及差異檢查已通過，待複審。 |
| 10 | 緊急修正 MTeam 子分類搜尋請求與快取隔離正確性 | 🔄 In Progress | general | 已改為傳送 `categories: [requested category ID]`，且僅快取 raw `category` 精確相符的項目；無相符項目會失敗並保留舊快取。目標測試、Pint、PHP 語法及差異檢查已通過，待獨立審查；未刷新既有快取。 |
| 11 | 以非 JavaScript 依賴的直接分類導覽修正電影／電視劇二級選單，並新增隨機 Swiper Hero 輪播 | 🔄 In Progress | general | 已取得使用者對 Swiper 依賴的明確確認；改用伺服器渲染且直接可點擊的分類導航，並從目前快取隨機挑選至多 10 筆有效 HTTP(S) 海報資料作為 Hero。已補正鍵盤焦點時的逐實例 autoplay 停止／離開輪播後恢復邏輯；待獨立複審，Human Review 維持 Pending。 |
| 12 | 調整快取種子標題與海報卡片互動、頁首品牌及 Hero 輪播控制 | 🔄 In Progress | general | 已從既有快取 raw `smallDescr` 推導含漢字的顯示標題並保留原標題 fallback；已更新卡片與品牌標記，待容器內驗證及獨立審查。 |
| 13 | 修正 Hero Swiper 海報原始比例呈現與響應式版面，並新增成人分類快取導覽 | 🔄 In Progress | general | 已依核准範圍實作：Hero 主海報改為 `object-contain` 的受限 inline 圖片與相鄰文字；成人頂層 `/adult`、9 個 child 路由及設定驅動的快取整合已加入。Human Review 維持 Pending，待驗證與獨立審查。 |
| 14 | 緊急修正成人分類搜尋模式與既有快取保護 | 🔄 In Progress | general | 以設定驅動方式僅為成人 category ID 的請求加入 `mode: 'adult'`；一般分類請求不得加入 mode，並保留精確分類隔離與失敗保留舊快取語意。 |
| 15 | Hero Swiper 單張原始比例海報與裝飾背景 | 🔄 In Progress | general | 保留每張投影片單張原始比例前景海報與文字；同張海報僅作低不透明度裝飾背景，保留目前快取資料、隨機最多 10 筆、導覽／分頁／無障礙及安全 fallback。 |
| 16 | 將二級分類導覽改為父類別情境式連結 | 🔄 In Progress | general | 電影、電視劇與成人父頁僅顯示各自 child links；首頁不顯示所有群組 child menu。以穩定直接連結、路由與伺服器 HTML active state 實作，不依賴脆弱的 JavaScript 選單；Human Review 維持 Pending。 |
| 17 | 首頁排除成人二級分類快取聚合與 Hero 選取 | 🔄 In Progress | general | 以成人父定義的 `exclude_from_homepage` 屬性推導首頁 child ID；僅影響根首頁的快取聚合、Hero 選取與顯示數量，不重複硬編碼 ID。成人父／子頁、成人導覽及刷新指令／排程維持不變；Human Review 維持 Pending。 |
| 18 | 提高 Hero Swiper 裝飾海報背景的可見度 | ⬜ Pending | — | 裝飾背景目前因 `opacity-20` 與近乎不透明的覆蓋層而幾乎不可見；調整為約 35–45% 的可感知背景存在感，並維持文字對比、前景原始比例海報、`alt=""` 裝飾語意及既有輪播行為。Human Review 維持 Pending。 |
| 19 | 校正 Hero Swiper 裝飾背景與右側覆蓋層的亮度平衡 | 🔄 In Progress | general | 裝飾背景已調為 `opacity-55`；覆蓋層維持左側 `.97/.88` 的強對比，右側降為 `.55/.35`。前景海報的原始比例與尺寸 class、裝飾語意維持不變。Human Review 維持 Pending；尚未取得指向此修訂的有效 Playwright 截圖，不宣告視覺驗證完成。 |
| 20 | 依發佈時間排序各分類快取種子 | ✅ Complete | code-reviewer | 實作與技術審查已完成；嚴格 round-trip 日期驗證會將無效日曆日期安全置後，並支援 ISO offset／小數秒、epoch 秒／毫秒及 date-only 格式。首頁快取讀取覆蓋禁止寫入、MTeam 呼叫與資料庫 interaction。驗證：38 tests／274 assertions。Human Review 與 Review Status 維持 Pending／未確認。 |
| 21 | 海報卡片遠端圖片失敗時使用使用者提供的本機備援海報 | 🔄 In Progress | general | 已為每張伺服器渲染卡片預先選定 `bg1.jpg` 或 `bg2.jpg`：缺失／無效海報直接使用該資產，遠端圖載入失敗時由無迴圈 `onerror` 使用同一預選資產。Hero 與空狀態不變；待驗證及獨立審查。Human Review 維持 Pending。 |
| 22 | 統一分類子項順序並調整成人子分類可見標籤 | 🔄 In Progress | general | 已依人工釐清重排電影、電視劇與成人 children；成人 child 僅移除「成人 」前綴，`429` 為「高清无码」。ID、slug、路由與快取行為不變；待獨立審查，Human Review 維持 Pending。 |
| 23 | 進一步提高 Hero Swiper 裝飾背景亮度 | 🔄 In Progress | general | 已將裝飾背景調為 `opacity-70`，右側覆蓋層調為 `.35/.20`，並新增伺服器輸出回歸斷言；待獨立技術審查。正確瀏覽器目標不可用，未取得有效桌面／行動版視覺驗證，故不得宣告視覺驗證完成。Human Review 維持 Pending。 |
| 24 | Hero Swiper 於前景海報載入失敗時安全移除投影片並提供全失敗漸層 fallback | 🔄 In Progress | general | Hero 伺服器端仍只選取有效 HTTP(S) 海報，且不得使用卡片專用的 `bg1.jpg`／`bg2.jpg`；瀏覽器端前景海報實際載入失敗時必須移除對應 slide、同步安全更新 Swiper 導覽／分頁／autoplay，全部失敗時顯示 Hero 漸層 fallback。Hero 外層／容器與前景海報移除圓角，但保留既有 border、shadow 與無障礙。Human Review 維持 Pending。 |
| 25 | 移除所有一般海報卡片的圓角 | 🔄 In Progress | general | 已移除卡片摘要外層圓角；圖片 overflow 容器維持無圓角。聚焦頁面輸出 class contract 測試已分別覆蓋摘要、圖片容器及具 `data-fallback-poster` 的一般卡片圖片不輸出 `rounded*`；保留 hover／focus、描述 overlay、shadow、版面與備援。分類導覽與快取未變更；待驗證及獨立審查。Human Review 維持 Pending。 |
| 26 | 擴充 `mteam:refresh-category` 支援零至多個已設定分類 ID | 🔄 In Progress | general | 已核准範圍：零個 ID 依既有設定順序完整刷新；一個或多個 ID 只依提供順序刷新，重複 ID 去重並保留首次出現順序。所有 ID 必須在任何刷新開始前完成數字及已設定分類驗證；任一無效或未設定 ID 則不刷新任何分類並回傳失敗。個別 API 失敗仍繼續後續分類、保留該分類舊快取，且只要有任一失敗即回傳失敗；排程無參數行為不變。 |
| 27 | 調整成人主導覽預設視覺為低調但可發現 | 🔄 In Progress | general | 已完成最小 Blade 樣式與伺服器標記 contract 測試；非 active 成人主連結預設／hover 與 header 融合，鍵盤僅保留 outline，父／child active state 維持既有可見慣例。待獨立審查；Human Review 維持 Pending。 |
| 28 | 將 Vite 生產建置資產納入 Git，供部署直接使用 | 🔄 In Progress | general | 已依核准範圍移除 `.gitignore` 對 `public/build` 的忽略並成功執行 production build；manifest 與全部當次產物待獨立審查。Review Status、Human Review 與 Reviewed By 維持 Pending／未確認。 |

### Status Legend

- ⬜ Pending — not started
- 🔄 In Progress — sub-agent working on it
- ✅ Complete — implemented and reviewed
- 🔴 Blocked — dependency or issue, needs resolution
- ⏭️ Skipped — not needed, with reason

## Review Log

| Date | Reviewer | Findings | Action Taken |
|---|---|---|---|
| 2026-11-24 | general | 已完成實作與測試案例；缺少 Docker Compose 設定，無法依規範於 app 容器執行 Pint／測試。 | 標示驗證阻塞，待具備容器環境後執行 |
| 2026-11-24 | code-reviewer | 最終審查無 Critical 或 Major findings。 | 審查完成 |
| 2026-11-24 | — | 使用者要求電影頁改為首頁，並回報尚未顯示資料。 | 待人工確認調整方案後實作 |
| 2026-11-24 | general | 使用者已確認將電影預覽改為根路由，且前台維持僅讀排程／指令刷新快取。 | 已以 `MovieController` 取代 welcome 根路由、移除 `/movies`，並更新功能測試；待審查與容器驗證 |
| 2026-11-24 | code-reviewer | 已完成已核准根路由調整之實作審查，無 Critical／Major findings。 | 審查完成；Docker Compose 設定缺失，app 容器內測試與 Pint 仍受阻 |
| 2026-11-24 | — | 使用者回報版式不符合預期，且全部海報顯示預設圖。 | 待人工確認新視覺與巢狀海報欄位解析方案 |
| 2026-11-24 | general | 已依核准追蹤項目完成已知 MTeam 巢狀媒體海報欄位解析、首頁視覺重設與測試調整。 | 待 code-reviewer 審查；既有快取須執行 `mteam:refresh-movies` 才會寫入新解析的海報 |
| 2026-11-24 | code-reviewer | 發現巢狀解析會遍歷通用媒體結構且接受 protocol-relative URL；前端關鍵標記測試不足。 | 修正進行中：限縮至 IMDb、Douban、mediaInfo、media_info，並補足安全與標記測試，待重新審查 |
| 2026-11-25 | general | 使用者核准修正 Laravel 13 排程 Facade 匯入。 | `routes/console.php` 已由 `Illuminate\Console\Scheduling\Schedule` 更正為 `Illuminate\Support\Facades\Schedule`；排程命令內容未變更，Human Review 與 Review Status 維持 Pending。 |
| 2026-11-24 | code-reviewer | 海報／版式補強複審完成，無 Critical／Major findings；Minor：可考慮補充圖片載入失敗情境的前端互動測試。 | 聚焦驗證成功：10 tests／36 assertions，並已完成語法、Pint 與差異檢查；Minor 僅作資訊性紀錄 |
| 2026-11-24 | — | 使用者要求以 Playwright 檢視現況，並希望介面更接近 Jellyfin 的專業媒體中心風格。 | Chromium 未安裝，待取得安裝授權並確認視覺改造方案 |
| 2026-11-24 | general | 已依核准方案完成 Jellyfin 啟發的深色媒體中心首頁：桌面側欄、行動頂欄、快取資訊、海報 Hero 與最新入庫媒體牆。 | 待執行 Pint、PHP 語法、功能測試與差異檢查，並交由 code-reviewer 複審 |
| 2026-11-24 | general | 依複審意見移除 Hero 海報的 CSS `url()` 插值，改以已跳脫的 `<img>` 圖層呈現；窄螢幕改為換行顯示快取刷新資訊。 | 已新增含 CSS 引號分隔字元的有效 HTTP(S) 海報 URL 功能測試；依要求未執行 PHP、Artisan 或 Composer，待複審 |
| 2026-11-24 | code-reviewer | 安全 Hero／行動版頂欄最終複審完成，無 Critical、Major 或 Minor findings。 | 聚焦檢查通過：`MoviePreview` 5 tests、`MovieTorrentCache` 6 tests、PHP 語法與差異檢查 |
| 2026-11-24 | Playwright | 已證實 `api.tmdsd.top` 載入 CSS 且頁面版面運作正常；但 100 張卡片仍使用佔位圖，因快取 API 資料沒有已解析的海報欄位。 | 需提供已去識別化的實際 MTeam 回應項目，才能確認精確欄位映射 |
| 2026-11-24 | — | 使用者確認種子列表已含封面 URL 欄位。 | 待人工確認擴充常見 URL 欄位別名與協議相對 URL 正規化方案 |
| 2026-11-24 | general | 已確認實際 MTeam 種子項目的頂層 `imageList` 為依優先順序排列的封面 URL 陣列。 | 正規化在既有頂層明確海報欄位之後、已知巢狀媒體欄位之前，逐項選取第一個有效絕對 HTTP(S) URL；未執行快取刷新 |
| 2026-11-24 | general | `MovieTorrentCacheTest` 聚焦驗證通過（6 tests／15 assertions），目標 PHP 檔案語法檢查與 Pint 格式化完成。 | 待 code-reviewer 複審；未執行 `mteam:refresh-movies` |
| 2026-11-24 | code-reviewer | `imageList` 海報正規化複審完成，無 Critical 或 Major findings。 | 聚焦驗證通過：6 tests／15 assertions，且已完成語法、Pint 與差異檢查；未執行快取刷新，仍須刷新後再進行即時瀏覽器驗證 |
| 2026-11-24 | Playwright | 快取刷新後於 `api.tmdsd.top` 驗證：桌面與行動版均正常渲染來自 `imageList` 的真實海報；遠端圖片請求成功，瀏覽器主控台未出現頁面錯誤。 | `imageList` 即時驗證追蹤項目完成；外部來源圖片若遭封鎖，仍會依設計使用失敗佔位圖 |
| 2026-11-24 | — | 使用者要求移除左側欄，改為頂部導航並新增電影、電視劇、遊戲分類。 | 待人工確認分類路由與快取內資料篩選方案 |
| 2026-11-24 | general | 已依核准方案實作頂部導覽、首頁／電影／電視劇／遊戲快取分類頁，以及設定檔驅動的 MTeam 原始 category ID 篩選。 | 待 code-reviewer 審查及容器內 Pint、語法與功能測試驗證 |
| 2026-11-24 | code-reviewer | 發現分類篩選在快取項目的 `raw` 非陣列時會發出警告，且缺少 `/tv-shows` 路由級分類與目前導覽狀態驗證。 | 修正已完成；`MoviePreviewTest` 7 tests／59 assertions、Pint、PHP 語法與差異檢查通過，待追蹤複審 |
| 2026-11-24 | code-reviewer | 頂部導航／分類路由最終複審完成，無 Critical、Major 或 Minor findings。 | 審查完成 |
| 2026-11-24 | Playwright | 於 `api.tmdsd.top` 桌面版確認側欄已移除、頂部導航固定、分類路由與海報均正常顯示。 | 行動版截圖因等待大量遠端圖片逾時未完成；程式碼與測試複審確認響應式導航行為 |
| 2026-11-24 | — | 使用者提供 MTeam 二級分類 ID，並要求教育紀錄歸入電視劇、動畫新增選單。 | 待人工確認四大類分別刷新與快取合併方案 |
| 2026-11-24 | — | 使用者要求二級分類名稱嚴格沿用其提供的 MTeam 分類命名。 | 待人工確認二級選單與單一 category 篩選方案 |
| 2026-11-24 | general | 已依最新核准範圍實作設定檔驅動的二級分類導航。電影與 TV 父頁聚合子 ID；子頁僅以快取項目的 `raw.category` 精確篩選。 | 新增電影／TV 子分類路由、動畫與遊戲單分類路由，未新增多分類刷新指令；待 code-reviewer 審查 |
| 2026-11-24 | code-reviewer | 發現 `.env.example` 保留未使用且與 `services.mteam.media_categories` 衝突的 `MTEAM_CATEGORY_*` 分類定義，並缺少無效電影／TV 子 slug 的 404 回歸測試。 | 已移除重複環境變數，並新增兩個路由約束的具名 404 功能測試；待容器內驗證與複審 |
| 2026-11-24 | general | 已於 `app` 容器完成追蹤修正驗證。 | `MoviePreviewTest`：21 tests／145 assertions 通過；`vendor/bin/pint --format agent`、`php -l tests/Feature/MoviePreviewTest.php` 與 `git diff --check` 通過。`--dirty` 因容器內未偵測到 Git 而不可用，未影響完整 Pint 格式化；待 code-reviewer 複審 |
| 2026-11-24 | — | 使用者要求每個二級分類分別刷新，而非合併為單一快取。 | 待人工確認獨立子類快取與可指定子類刷新命令方案 |
| 2026-11-24 | general | 使用者回報主導航顯示英文的回歸。 | 修正進行中：首頁、電影、電視劇、動畫、遊戲主選單改為中文，待目標功能測試、Pint 與審查 |
| 2026-11-24 | general | 主導航中文標籤修正已完成目標驗證。 | `MoviePreviewTest`：21 tests／145 assertions 通過；Pint、兩個變更 PHP 檔語法檢查與差異檢查通過；Task 6 維持進行中，待審查 |
| 2026-11-24 | code-reviewer | 主導航中文標籤修正複審完成，無 Critical 或 Major findings；Minor 僅為測試精確性建議。 | `MoviePreviewTest`：21 tests／145 assertions 與差異檢查通過；Task 6 審查完成 |
| 2026-11-24 | general | 已依確認範圍改為每個已配置 child category ID 一個快取 key；單一指定 ID 刷新只寫入該 key，失敗或空結果保留舊值。 | `mteam:refresh-category {categoryId?}` 無參數時依序刷新所有已配置 ID；個別失敗不中斷後續分類，最後以成功／失敗／種子數摘要並回傳失敗狀態 |
| 2026-11-24 | general | 處理獨立分類刷新審查的 Minor findings。 | 已匯入全域 `InvalidArgumentException`；無參數命令測試比對完整設定 ID 順序；新增預設 cron、具名與無重疊鎖設定測試。聚焦測試 8 tests／30 assertions、Pint、四個 PHP 語法檢查、`schedule:list` 與差異檢查皆通過。 |
| 2026-11-24 | code-reviewer | 獨立子分類快取刷新最終審查完成，無 Critical、Major 或 Minor findings。 | 實作與複審完成；聚焦驗證 12 tests／38 assertions、PHP 語法與差異檢查通過。無參數命令會刷新所有已設定分類；可選 category ID 僅刷新指定分類。 |
| 2026-11-24 | Playwright | 使用者回報電影／電視劇分類選單無回應；即時 DOM 仍包含原生 `<details>` 選單，程式化開啟後可看見並存取子分類連結，但 Playwright 實體點擊會等待元素穩定而未完成。 | 待人工確認後，以受控的可存取按鈕互動取代原生 `<details>`；保留現有路由與快取僅讀行為，並執行功能測試及桌面／行動版 Playwright 驗證。 |
| 2026-11-24 | general | 已完成 task 8 實作與驗證：伺服器渲染的受控按鈕／子連結、`aria-expanded`／`aria-controls`、桌面互斥 popover、Escape 與點擊外部關閉、行動版頁內展開；未進行 API、資料庫或快取寫入。 | `MoviePreviewTest` 5 tests／45 assertions 與 `npm run build` 通過；host Chrome 實體點擊已確認桌面與行動版電影／電視劇選單顯示子連結，桌面 Escape／點擊外部關閉，且桌面／行動均可進入 `/movies/movies-sd`。待獨立審查。 |
| 2026-11-24 | general | 修正審查 Minor：Escape 改為優先關閉事件目標所屬的選單控制器；無所屬控制器時，關閉已開啟的桌面選單，否則關閉所有已開啟的行動選單。 | `npm run build` 與 `MoviePreviewTest`（5 tests／45 assertions）通過。host Chrome 行動版實體驗證同時開啟 Movie／TV 選單後，在 TV 子連結按 Escape：TV 關閉並回復焦點、Movie 保持開啟。PHP 功能測試僅覆蓋伺服器輸出標記，無法觀察此 JavaScript 事件路由，未新增無效回歸測試。 |
| 2026-11-24 | Playwright | 即時驗證動畫連結會導向 `/animation`。 | 導覽操作已正確接線；使用者端若仍觀察到舊行為，應獨立排除瀏覽器頁面／快取過期問題。 |
| 2026-11-24 | — | 使用者確認新方向：首頁不應顯示聚合後的所有項目。 | 待人工確認後實作：依設定 child category 順序，每類最多顯示 12 筆，12 類最多 144 筆；父類與分類頁維持完整結果。 |
| 2026-11-24 | — | 使用者追加要求：`mteam:refresh-category` 的預設快取期限由 3,600 秒調整為一天。 | 待實作與驗證；保留既有 `MTEAM_MOVIE_CACHE_TTL` 明確覆寫。 |
| 2026-11-24 | general | 已完成首頁每類 12 筆快取限制與一天預設 TTL。 | `MovieTorrentCacheTest`、`MoviePreviewTest` 共 11 tests／65 assertions 通過；目標 PHP 語法檢查及 Pint 通過，待獨立審查。 |
| 2026-11-24 | code-reviewer | 發現 `MovieTorrentCache` 對 `services.mteam.cache_ttl` 保留衝突的 3,600 秒 fallback（Major）；另建議補足首頁快取聚合無 API 呼叫及非預設每類上限覆蓋（Minor）。 | 已移除服務內 fallback，並新增對應回歸測試。`MovieTorrentCacheTest` 8 tests／23 assertions、完整 Pint、兩個目標 PHP 語法檢查及 `git diff --check` 均通過；容器內 Pint 的 `--dirty` 因未偵測 Git 而不可用。待複審。 |
| 2026-11-24 | — | Live cache tinker 顯示 keys `401`、`419`、`403`、`405`、`423` 的第一筆標題相同，且其 `raw.category` 都是 `'419'`。目前客戶端傳送單數 `category`，MTeam 種子搜尋會忽略該欄位。 | 新增緊急待核准正確性任務：請求改用複數 `categories` 陣列，並在快取寫入前精確驗證每筆原始分類；不呼叫 web API、不影響獨立 child cache 或使用者資料。 |
| 2026-11-24 | general | Task 10 已完成實作與目標驗證：搜尋請求傳送 `categories` 單元素陣列；回應僅在 raw `category` 與請求 ID 完全相符時正規化與寫入；無相符項目會失敗並保留該 child key 的既有 payload。 | `MteamApiClientTest`、`MovieTorrentCacheTest`、`RefreshMteamCategoryCommandTest` 共 16 tests／74 assertions 通過；目標 Pint、PHP 語法及 `git diff --check` 通過。未執行刷新命令，待 code-reviewer 審查與人工部署後刷新。 |
| 2026-11-24 | — | 使用者回報電影／電視劇二級選單持續無法顯示或點擊，要求可直接存取、不依賴 JavaScript 的分類導航，並要求隨機 Swiper Hero 輪播。 | 待人工確認計畫，尤其是安裝尚未存在的 Swiper 套件依賴；本機 host Chrome 重新檢查受 `ERR_BLOCKED_BY_CLIENT` 阻擋，先前 live 測試成功，故以本次使用者回報為準。 |
| 2026-11-24 | general | Task 11 已完成實作與初步驗證：電影／电视剧二級連結改為常態呈現的伺服器 HTML；Hero 僅隨機使用目前快取中至多 10 筆有效 HTTP(S) 海報，並以 Swiper 提供輪播增強。 | `MoviePreviewTest` 6 tests／53 assertions、`npm run build`、完整 Pint、目標 PHP 語法與 `git diff --check` 通過；host 瀏覽器連線 `https://api.tmdsd.top` 時為 `ERR_CONNECTION_REFUSED`，未採取繞過措施，待獨立審查。 |
| 2026-11-24 | general | 修正 Swiper Hero 無障礙 Major finding：新增中文播放／暫停控制、`aria-pressed` 狀態，並於輪播內取得焦點時停止自動播放。 | 已補充伺服器渲染標記回歸測試；外部瀏覽器驗證受拒絕／阻擋，未採取繞過措施。 |
| 2026-11-24 | — | 使用者提供實際快取項目證據：raw `smallDescr` 為 `罗伯・洛：小教父本色/Rob Lowe: The Outsider | 2026 | ...`，要求封面標題沿用既有中文內容而非翻譯。 | 新增 Task 12，待人工確認後才實作。 |
| 2026-11-24 | general | 已依使用者核准實作 Task 12：既有快取會在讀取時從 raw `smallDescr` 提取前導含漢字片段作為顯示標題；卡片 hover／鍵盤聚焦時顯示完整描述 overlay，並移除詳情與 Hero 暫停控制。 | 待以 app 容器執行測試、Pint、語法、Vite 建置與差異檢查；人工 Review 欄位維持 Pending。 |
| 2026-11-24 | general | 修正 Swiper Hero 鍵盤焦點無障礙 Major finding：焦點進入各輪播時停止該實例 autoplay，焦點離開後僅在可恢復且滑鼠未停留時恢復；保留 hover pause、導覽／分頁與直接分類連結。 | 待獨立複審；Human Review 維持 Pending。 |
| 2026-11-24 | — | 使用者要求 Hero Swiper 的直式海報維持原始比例，不得被延展成全寬 banner。 | 新增待人工確認 Task 13：改採投影片內含比例海報與文字的響應式版面；如使用裝飾背景，必須使用不變形的低調圖層；保留既有可及性、autoplay 焦點與資料量 fallback 行為。 |
| 2026-11-24 | — | 使用者要求將成人分類作為 Task 13 的待核准延伸。 | 待人工確認：新增成人頂層導覽、9 個獨立 child 快取／頁面／排程 ID，並將成人 child 依既有首頁規則納入每類最多 12 筆的預覽。 |
| 2026-11-24 | general | 使用者已核准 Task 13。 | 已實作設定驅動成人分類、原始比例 Hero 與對應測試；待容器內驗證與獨立審查，Human Review 維持 Pending。 |
| 2026-11-24 | general | Task 13 審查要求補足 Hero 的 0／1／少於 10 筆海報伺服器輸出涵蓋。 | 新增無有效海報的漸層 fallback、單張原始比例無控制項，以及兩張有效海報精確投影片／控制項的功能測試；未變更 Review Status、Human Review 或 Reviewed By。 |
| 2026-11-24 | general | JS focus／hover 未新增測試依賴或非慣例的原始碼斷言。 | 可用 Chrome 目前指向的已部署頁面未包含 Task 13 的成人導覽，並非此工作區可驗證的修訂版本；保留既有靜態實作／審查涵蓋，待有目前修訂的瀏覽器目標再驗證。 |
| 2026-11-24 | — | Live API 診斷：`categories: ['431']` 單獨請求回傳 SUCCESS、total 0；加入 `mode: 'adult'` 後回傳 total 554，且結果 raw `category` 為 `431`。 | 新增 Task 14 緊急待核准修正：以設定驅動方式為成人分類提供 `mode: adult`；部署後由人工執行無參數全量刷新。 |
| 2026-11-24 | — | 使用者確認成人刷新模式修正，並追加 Hero 多卡原始比例與情境式二級導覽需求。 | 新增 Tasks 15–16；所有工作均待依既有流程執行，Review Status、Human Review 與 Reviewed By 保持未確認。 |
| 2026-11-24 | — | 使用者更正 Hero 範圍：不使用同 viewport 多卡輪播。 | 保留單張原始比例前景海報與文字，新增同張海報的低不透明度裝飾背景；Human Review 仍為 Pending。 |
| 2026-11-24 | general | 強化 Task 16 情境式導覽回歸測試，避免只驗證某一預期連結而漏掉其他 `aria-current="page"`。 | 對電影、電視劇與成人的父頁及 child 頁，分別在主要與情境式次級導覽範圍內斷言唯一目前頁連結及其預期 href；Human Review 維持 Pending。 |
| 2026-11-24 | — | 使用者要求首頁不納入任何成人二級分類資料。 | 新增 Task 17，待人工確認後才實作：從設定推導成人 child ID，僅於首頁快取聚合、Hero 選取及計數排除；成人路由、導覽、指令與排程不變。 |
| 2026-11-25 | — | 使用者回報 Hero Swiper 同張海報的裝飾背景存在但幾乎不可見。 | 新增 Task 18，待人工確認後才可調整背景不透明度、模糊與漸層；須完成視覺及前端 build 驗證。 |
| 2026-11-25 | — | 現行來源的裝飾背景不透明度約為 40%，但右側覆蓋層約為 `rgba(16, 17, 20, .9)`，仍令海報過暗；Playwright 無法驗證，因 localhost 指向另一個應用程式且遠端目標為 `ERR_BLOCKED_BY_CLIENT`。 | 新增 Task 19，待人工確認後校正背景／覆蓋層平衡；未取得指向正確應用程式的有效 Playwright 截圖前，不得宣告視覺驗證完成。 |
| 2026-11-25 | general | 已依核准 Task 19 調整 Hero 裝飾背景／覆蓋層並更新伺服器輸出標記測試。 | `MoviePreviewTest` 15 tests／209 assertions、`npm run build`、Pint、PHP 語法與差異檢查通過。`--dirty` Pint 因容器未偵測 Git 而不可用，已執行完整 Pint。Human Review、Review Status 與 Reviewed By 維持 Pending／未確認，且未進行視覺驗證。 |
| 2026-11-25 | — | 使用者要求各分類既有快取依發布日期由新到舊排序，聚合仍保留設定分類順序，且首頁限制在各類排序後執行。 | 新增 Task 20；Review Status、Human Review 與 Reviewed By 維持 Pending／未確認。 |
| 2026-11-25 | — | 使用者要求分類內快取種子按發佈時間由新至舊排序，並要求首頁限制及父類／分類頁聚合均使用排序後的序列。 | 新增 Task 20，待人工確認後才可實作；需涵蓋單一快取分類排序、首頁排序後每類 12 筆，以及父類聚合維持分類設定順序但分類內日期遞減的回歸測試。 |
| 2026-11-25 | — | 使用者提供 `public/images/bg1.jpg`、`public/images/bg2.jpg` 作為海報卡片的本機備援資產。 | 新增 Task 21，待人工確認後才可實作：缺失或無法讀取的遠端卡片海報須以每個渲染元素穩定決定的方式選用一張備援，並包含 client-side `onerror` 處理；不得將備援資產當作 Hero 真實資料或改變空狀態。 |
| 2026-11-25 | general | Task 21 實作完成待驗證：卡片於伺服器渲染時預先選定本機 `bg1.jpg` 或 `bg2.jpg`，缺失／無效 URL 直接使用該資產；遠端 URL 保留原始 src，失敗時透過 `data-fallback-poster` 與一次性 onerror 切換到相同資產。 | 已新增聚焦伺服器輸出測試；Human Review、Review Status 與 Reviewed By 維持 Pending／未確認。 |
| 2026-11-25 | — | 使用者要求重排分類 children，讓設定顯示、情境式導覽、首頁／父頁聚合與無參數刷新採一致順序；並移除成人 child 可見標籤的「成人 」前綴。 | 使用者已釐清 `429` 為「高清无码」，並核准 Task 22 進入實作；Human Review 仍維持 Pending。 |
| 2026-11-25 | — | 使用者回報 Hero Swiper 同張海報的裝飾背景在 Task 19 調整後仍然過暗。 | 新增 Task 23，待人工確認後才可將背景約由 `opacity-55` 提高至 `opacity-70`，並進一步降低右側覆蓋層；正確瀏覽器目標目前不可用，故不得宣告視覺驗證完成。 |
| 2026-11-25 | general | Task 23 已完成樣式實作，並新增 Hero 裝飾背景與覆蓋層輸出的回歸斷言。 | 裝飾背景為 `opacity-70`，右側覆蓋層為 `.35/.20`；待獨立技術審查。正確瀏覽器目標不可用，未進行有效桌面／行動版視覺驗證，故不宣告視覺驗證完成。 |
| 2026-11-25 | — | 使用者要求 Hero Swiper 排除無海報資料、在前景海報實際載入錯誤時移除空白投影片，並取消 Hero 圓角。 | 新增 Task 24，待人工確認後才可實作；正確瀏覽器目標仍不可用，不能預先宣告視覺驗證完成。 |
| 2026-11-25 | general | Task 24 已完成實作，待獨立審查與驗證。 | Human Review、Review Status 與 Reviewed By 維持 Pending／未確認；正確瀏覽器目標不可用時不得宣告實際圖片錯誤或視覺驗證完成。 |
| 2026-11-25 | — | 使用者釐清所有海報封面均不應有圓角；Task 24 的範圍僅移除 Hero／前景海報圓角，正常卡片摘要與圖片仍保有 `rounded-lg`。 | 新增 Task 25，待人工確認後才可移除一般海報卡片外層與圖片圓角；分類導覽與快取不得變更。 |
| 2026-11-25 | general | Task 25 測試補強：一般卡片的 `data-fallback-poster` 圖片不輸出 `rounded*` class。 | 僅更新聚焦頁面輸出測試與任務紀錄；Human Review、Review Status 與 Reviewed By 維持 Pending／未確認。 |
| 2026-11-25 | — | 使用者要求 `mteam:refresh-category` 接受零、一個或多個已設定數字分類 ID，並明確定義去重、預先驗證、部分 API 失敗與排程相容性。 | 新增 Task 26，待人工確認後才可實作；Human Review、Review Status 與 Reviewed By 維持 Pending／未確認。 |
| 2026-11-25 | — | 使用者要求成人主導覽預設與滑鼠 hover 視覺均與頁首背景融合、刻意低調，且不提供 hover 揭示或 tooltip；連結仍可操作，鍵盤僅以可見 `:focus-visible` outline 發現。 | 更新 Task 27，待人工確認後才可實作；成人父／child active state 可依既有慣例維持可見，不得改變語意、路由或快取。 |
| 2026-11-25 | general | 已依核准 Task 27 完成成人主導覽低調樣式及 focused server markup contract 測試。 | `MoviePreviewTest` 18 tests／272 assertions、`npm run build`、Pint、測試檔語法與差異檢查通過。Human Review、Review Status 與 Reviewed By 維持 Pending／未確認，待獨立審查。 |
| 2026-11-25 | — | 使用者明確要求將 Vite production `public/build` 產物提交至 Git，讓 production 部署只更新程式碼而不執行 npm build。 | 新增 Task 28，待人工確認後才可修改 Git 忽略規則、產生並納入建置資產；不新增 README 或其他部署文件，除非現有流程確有必要。 |

## Notes

- 使用者已確認先前對話中的實施範圍並要求開始實作。
- API endpoint 與 API key 由使用者以環境變數提供，程式碼不得寫入憑據。
- 每個已設定子分類預設抓取最新 100 筆資料，刷新頻率預設為每 15 分鐘；快取預設保存一天（86,400 秒），並可透過既有 `MTEAM_MOVIE_CACHE_TTL` 覆寫。
- 根路由由 `MovieController` 提供，僅讀取已由排程或 `mteam:refresh-category` 指令刷新之快取；未成功執行刷新命令或未啟動排程時，頁面會顯示空狀態。
- 目前僅讀取種子頂層海報欄位；需擴充 IMDb、Douban 與媒體資訊等巢狀結構後重新刷新快取。
- 巢狀海報解析已限縮於已知 IMDb、Douban 與媒體資訊欄位；不會在首頁請求外部 API。既有快取資料須執行 `mteam:refresh-category` 才會補入新解析的海報。
- Hero 僅會使用第一筆快取電影的有效 HTTP(S) 海報作為已跳脫的 `<img>` 背景層；快取未包含有效海報時會使用本機漸層背景。現有快取需執行 `mteam:refresh-category` 才能填入可視海報。
- Playwright 初次檢視時曾發現當時快取的 100 筆資料未含已解析海報欄位，因此全部卡片使用佔位圖；此為刷新前的歷史結果。
- `imageList` 為 MTeam 已知的頂層封面 URL 列表，依陣列順序保留優先權；解析僅接受列表內直接提供的有效絕對 HTTP(S) URL，且既有頂層明確海報欄位優先於它。
- `imageList` 海報正規化複審無 Critical 或 Major findings。刷新快取後，Playwright 已在 `api.tmdsd.top` 的桌面與行動版確認真實海報渲染、遠端圖片請求成功，且主控台沒有頁面錯誤；個別外部來源圖片若被封鎖，會依既有設計回退至佔位圖。
- 分類頁僅以 `raw.category` 比對 `services.mteam.media_categories`；不會呼叫 MTeam API 或讀取資料庫，未映射的原始 category 值會完整保留在快取資料中且不會被猜測分類。
- Playwright 已於 `api.tmdsd.top` 桌面版確認側欄移除、固定頂部導航、分類路由及海報顯示；行動版截圖嘗試因等待大量遠端圖片逾時，但程式碼與測試複審已確認響應式導航。
- 二級分類導覽定義位於 `services.mteam.media_categories`，集中提供標籤、類型、路由與原始 category ID；前台不會觸發 API、資料庫或快取刷新。
- 子類快取 key 為 `mteam.movies.category.{categoryId}`，payload 僅含 `torrents` 與 `refreshed_at`。首頁僅聚合未標記 `exclude_from_homepage` 的父分類 child ID；電影／電視劇／成人父頁聚合各自 child ID；子頁、動畫及遊戲只讀取各自 ID 的 key。聚合顯示的刷新時間為目前顯示資料中最新的 child `refreshed_at`。
- `mteam:refresh-category 401` 只搜尋並原子性更新 `401` 的 child key；可使用 `mteam:refresh-category 401 419 412` 依提供順序刷新多個 child key，重複 ID 僅處理首次出現的一次。無參數執行時會依設定順序逐一刷新所有 child IDs；所有指定 ID 均在刷新前完成驗證，任一無效 ID 不會造成 API 呼叫或快取寫入。每個分類的 API 失敗或空結果不會寫入快取，也不會停止其餘分類。排程每 15 分鐘執行一次無參數指令，使用名稱 `mteam:refresh-categories` 及 30 分鐘 `withoutOverlapping` 鎖。
- 分類選單問題的即時證據：DOM 中保有原生 `<details>` 元素，程式化開啟可顯示並存取子分類連結；但 Playwright 的實體點擊會卡在元素穩定等待。後續方案應改為受控、具 `aria-expanded` 等明確狀態的按鈕互動，桌面呈現 popover、行動版呈現可展開區段，且不得改變既有分類路由或前台僅讀快取設計。
- 已完成的 Playwright 即時驗證顯示動畫連結會導向 `/animation`，表示前端導覽動作已接線。若使用者端仍顯示舊結果，應另行檢查該瀏覽器分頁是否為過期頁面或受本機快取影響。
- 首頁限制僅適用根路由：依 `services.mteam.media_categories` 的未標記 `exclude_from_homepage` child category 設定順序，各分類從快取最多取 12 筆。成人父分類標記為 `exclude_from_homepage`，其 9 個 child 不會出現在首頁、Hero、首頁總數或首頁最新刷新時間；服務／控制器不得存取 API、資料庫或改寫快取。電影／電視劇／成人父頁及所有分類頁仍保留完整聚合／篩選結果，除非使用者另行要求變更。
- 緊急正確性任務的現場證據：透過快取 tinker 檢查，child keys `401`、`419`、`403`、`405`、`423` 均出現相同第一筆標題，且 `raw.category` 均為字串 `'419'`。根因為目前 MTeam 搜尋請求傳送單數 `category`，該 API 會忽略此欄位。
- 待核准的修正範圍：每次 child 刷新 API body 必須傳送 `categories: [requested numeric category ID]`；服務在寫入該 child key 前，必須確認每一個回應項目的 `raw.category` 與所請求 category ID 完全相符。若沒有任何相符項目，必須拋出錯誤並保留既有快取，不能以錯誤分類、空結果或混合結果覆寫。維持既有獨立 child cache、前台不呼叫 web API，且不讀寫使用者資料。
- 必須新增 request-payload 與 child cache 隔離的回歸測試。部署後需由人工執行一次無參數 `mteam:refresh-category` 完整刷新，以修復既有錯置快取。
- Task 10 實作已改以複數 `categories` 提交每次指定的 child ID；回應僅於頂層 raw `category` 為同值字串或整數時才正規化與寫入。若無任何相符的可展示項目，指令會以既有失敗輸出及非零狀態結束，該 child key 不會被覆寫。既有快取刻意未在本次實作中刷新；部署後須由人工執行 `docker compose exec -T -w /var/www/html/api app php artisan mteam:refresh-category`。
- Task 11 待核准範圍：電影與電視劇必須提供無 JavaScript 仍可見、直接可點擊並可到達既有子分類路由的二級分類導覽；不得依賴或假設既有受控 popover 能正常運作。可在 JavaScript 可用時增強體驗，但連結可達性與可見性不得受其影響。
- Hero 輪播須在每次伺服器渲染時，僅從目前已讀取的快取資料中隨機選取最多 10 筆具有效 HTTP(S) 海報的記錄；前台不得呼叫 API 或資料庫，資料為空或不足 10 筆時須安全渲染可用資料，沒有合格項目時沿用既有 Hero fallback。
- Swiper 目前未安裝。實作此輪播需新增前端套件依賴，必須先取得使用者明確確認；未確認前不得修改依賴或應用程式原始碼。
- Task 11 驗證計畫：補足伺服器輸出、快取資料篩選／空資料 fallback 的功能測試，執行相關測試及前端 build，並以桌面與行動瀏覽器確認二級連結直接可見與可點擊、Hero 正常輪播及 fallback。此次本機 host Chrome 重新檢查遭 `ERR_BLOCKED_BY_CLIENT` 阻擋；先前 live 測試曾成功，與本次使用者持續回報衝突時，以本次使用者回報為現行問題依據。
- Task 12 待核准範圍：卡片顯示標題須從已快取項目的 raw `smallDescr` 前導片段推導，且僅在該片段含有中文文字時採用；若存在 `/`，優先使用斜線前的中文部分，例如 `罗伯・洛：小教父本色/Rob Lowe: The Outsider | 2026 | ...` 顯示為 `罗伯・洛：小教父本色`。沒有中文內容或資料格式不符時，維持既有 torrent title fallback。此變更只讀取既有快取 raw payload，不得呼叫 API、資料庫、改寫快取或要求重新生成快取。
- Task 12 亦待核准調整：頁首與文件 title 的可見應用名稱改為「小甜甜影院」；移除 Hero 輪播暫停控制，保留 Swiper 預設自動輪播；移除海報卡片右上角「詳情」指示。卡片在滑鼠 hover 或鍵盤 focus 時，於底部顯示完整描述的 overlay，離開 hover 或 focus 後隱藏；必須維持可存取的 focus 行為，且不得造成版面位移。
- Task 12 驗證計畫：新增或更新 raw `smallDescr` 中文提取、斜線處理與既有 title fallback 的測試，以及頁面輸出／可存取互動的適當回歸測試；執行受影響測試、PHP Pint（若修改 PHP）與 `npm run build`。
- Task 13 待核准範圍：Hero Swiper 的每張投影片必須以海報原始／原生長寬比顯示，不得把單張直式海報拉伸、裁切成為全寬橫幅或作為會失真的背景。優先採用海報 `object-fit: contain` 的受限尺寸區塊搭配相鄰文字內容；如保留視覺背景，可使用同一海報的低調、模糊或遮罩裝飾層，但其比例不得變形，且不可取代主要可讀海報。
- Task 13 響應式與回歸要求：桌面版應呈現海報與文字並列，行動版應在不拉伸海報的前提下改為易讀的堆疊或合適的緊湊版面。必須保留目前的 Swiper 導覽／分頁、鍵盤焦點進入時停止 autoplay、焦點離開後既有條件式恢復 autoplay、hover 行為，以及 0 筆、1 筆與少於 10 筆合格海報資料時的安全 fallback。實作後應更新適當的輸出或互動回歸測試，執行受影響測試、`npm run build`，並以桌面及行動瀏覽器驗證原始比例、輪播與 fallback。
- Task 13 待核准延伸（成人分類）：在既有設定式分類結構中新增頂層「成人」導覽與父頁，child 順序及供顯示的名稱／原始 MTeam ID 必須嚴格為：`431` 成人 蓝光有码、`432` 成人 蓝光无码、`410` 成人 高清有码、`429` 成人 高清无码、`424` 成人 标清有码、`430` 成人 标清无码、`437` 成人 标清有码、`426` 成人 DVD无码、`412` 成人 动画。兩個同名的「成人 标清有码」不得改動可見名稱；必須分別設定唯一、可長期穩定的 slug（例如含 ID）與相異的路由／導覽識別，避免連結、測試選擇器或設定鍵衝突。
- 成人每個 child 必須沿用一個 category ID 對應一個獨立 cache key 的既有設計，並提供只讀該 child cache 的分類頁；成人父頁僅聚合這 9 個 child caches。`mteam:refresh-category` 無參數與既有每 15 分鐘排程必須把全部新增 ID 納入設定順序，指定 ID 時仍只刷新該 ID。維持目前請求 `categories: [requested category ID]`、原始 `raw.category` 精確比對、成功且非空才原子寫入、失敗／空結果保留既有 cache，以及既有 TTL／鎖定／錯誤處理語意，不能因本擴充而變更。
- 成人導覽要求：桌面與行動版均須以伺服器渲染、無 JavaScript 仍直接可見且可點擊的 child links 呈現；頂層「成人」、父頁與所有 child 頁須具穩定具名路由及目前頁面狀態／無障礙標記。所有 web 路徑（首頁、成人父頁、成人 child 頁）只能讀取既有快取，不得呼叫 MTeam API、讀寫資料庫或刷新快取。
- 首頁整合要求：除非使用者明確要求成人不顯示於首頁，成人 9 個已設定 child 必須依設定順序納入根路由的既有每 child 最多 12 筆限制，因此成人最多增加 108 筆預覽；成人父頁及 child 頁仍應依既有規則呈現完整快取結果。測試計畫須涵蓋所有新 ID 的設定／無參數指令與排程納入、指定 child 的快取隔離與錯誤保留、成人父頁聚合、兩個同名 child 的不衝突穩定路由、首頁每 child 12 筆限制、桌面及行動直接連結、無效 slug 404，以及確認 web 請求不觸發 API／資料庫／快取寫入。Human Review 與 Review Status 在此延伸獲明確確認前維持 Pending。
- Task 13 目前實作的成人父頁為 `/adult`（路由名 `adult.category`）；child 路由名為 `adult.child-category`，並依設定生成：`/adult/adult-bluray-censored`、`/adult/adult-bluray-uncensored`、`/adult/adult-hd-censored`、`/adult/adult-hd-uncensored`、`/adult/adult-sd-censored-424`、`/adult/adult-sd-uncensored`、`/adult/adult-sd-censored-437`、`/adult/adult-dvd-uncensored`、`/adult/adult-animation`。部署後應執行 `docker compose exec -T -w /var/www/html/api app php artisan mteam:refresh-category` 寫入新增分類快取。
- Task 14 緊急待核准範圍：現場 API 診斷確認 `categories: ['431']` 單獨請求雖為 SUCCESS 但 total 為 0；同一請求加入 `mode: 'adult'` 後 total 為 554，且回應 raw `category` 為 `431`。不得記錄憑據。應以設定驅動方式讓成人 child 定義或成人父層設定提供 `mode: adult`，刷新服務據此將 `categories: [requested category ID]` 與 `mode: 'adult'` 一併傳送；非成人分類請求不得加入 mode，既有行為必須維持不變。
- Task 14 必須保留既有 raw `category` 精確比對及「無相符項目／失敗時保留既有 child cache」語意。測試須確認成人請求同時傳送 `categories` 陣列與 `mode: 'adult'`、一般分類只傳送既有 `categories` 且不傳 mode，以及成人模式下無相符結果仍不覆寫舊快取。部署後由人工執行一次無參數 `mteam:refresh-category` 全量刷新所有分類快取。Review Status 與 Human Review 必須維持 Pending，待明確人類確認後才可實作。
- Task 14 已取得本次使用者確認，可進入實作排程；設定應以成人群組或 child 定義辨識成人 category ID，而非以散落的 ID 判斷。每個成人 child 請求必須傳送 `categories: [requested category ID]` 與 `mode: 'adult'`；電影、電視劇、動畫與遊戲等非成人 ID 必須只保留既有 `categories` payload。不得改變 cache key、TTL、排程、原始 `raw.category` 精確比對或失敗／空結果保留舊值的保護。
- Task 15 現行範圍：Hero Swiper 每張投影片保留單張原始比例、`object-fit: contain` 的前景海報及相鄰文字；不得以多卡或多海報同 viewport 輪播取代該結構。同張海報可作為低不透明度、不可互動且 `alt=""` 的裝飾背景，得使用 `cover`、模糊與漸層，但不能取代可讀主圖或重複有意義的圖片敘述。保留隨機選取、目前只讀快取資料來源、Swiper 導覽、分頁、鍵盤與焦點／hover autoplay 行為、無障礙標記，以及 0、1、少於 10 筆有效海報時的 fallback。測試應覆蓋裝飾背景、前景原始比例、無有效海報 fallback 與單筆安全呈現；另執行前端 build 與可用的桌面／行動瀏覽器驗證。
- Task 16 待執行範圍：二級分類導航改為情境式伺服器 HTML。前往或點擊「電影」父頁時，只顯示電影 children；「電視劇」父頁時，只顯示電視劇 children；「成人」父頁時，只顯示成人 children。根首頁不得同時暴露所有群組的 child menu。導覽必須保留既有精確可見分類標籤、既有 child 路徑與直接連結；選取正確的父頁直接連結及目前頁面／群組 active state，使用具名路由、`aria-current` 等 HTML-first 可及性機制，且在 JavaScript 不可用時完整運作，不得依賴 popover、切換或其他脆弱 JavaScript 選單。測試應覆蓋首頁不輸出各群組 child menu、電影／電視劇／成人各自父頁只輸出所屬 children、既有直接 child routes 與 active states，並確認 web 請求維持僅讀快取且不觸發 API、資料庫或快取寫入。Review Status、Human Review 與 Reviewed By 均必須維持未確認。
- Task 16 審查 Minor coverage gap 已補足：`MoviePreviewTest` 以結構化 DOM 斷言確認電影、電視劇與成人的父頁及 child 頁，在主要與各自情境式次級導覽中均有正確的 `aria-current="page"` active link。Review Status、Human Review 與 Reviewed By 均維持 Pending／未確認。
- Task 17 待人工確認範圍：根首頁必須從快取聚合、Hero 候選及首頁顯示總數中排除所有成人二級分類 ID。排除集合必須由既有設定中的成人群組或 child 定義推導，不得在控制器、服務或測試中重複列出成人 ID。首頁仍只讀取既有快取，不得呼叫 MTeam API、資料庫、刷新指令或寫入快取。
- Task 17 不得改變成人父頁 `/adult`、所有成人 child 頁、成人主／情境式導覽、成人 child cache key，或 `mteam:refresh-category` 的指定分類與無參數全量刷新行為；既有每 15 分鐘排程也必須繼續納入成人 ID。電影、電視劇、動畫與遊戲的首頁／分類頁行為維持既有設定式規則。
- Task 17 測試要求：驗證首頁輸出不含任何成人 child 快取項目、成人項目不會成為 Hero，且首頁計數不包含成人資料；另驗證成人父頁與至少一個成人 child route 仍能讀取其既有快取並正常顯示。測試資料應從設定取得成人定義，避免以重複 ID 清單掩蓋設定變動。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認。
- Task 18 待人工確認範圍：Hero Swiper 的同張海報裝飾背景已存在，但目前 `opacity-20` 疊加近乎不透明的覆蓋層，使背景效果幾乎無法察覺。應提高背景實際可見度至約 35–45%，並視需要降低模糊、重新平衡漸層與遮罩；文字區及控制項必須維持足夠對比與可讀性。
- Task 18 不得改變前景海報的單張原始比例、`object-fit: contain` 呈現或尺寸限制；裝飾背景必須繼續不可互動、使用空替代文字（`alt=""`）／裝飾性語意，且不得新增重複的可讀圖片內容。資料來源、隨機選取上限、Swiper 導覽／分頁、鍵盤焦點與 hover autoplay 行為，以及 0、1、少於 10 筆有效海報時的 fallback 均須維持既有行為。
- Task 18 驗證要求：更新適當的伺服器輸出或視覺相關回歸測試（如現有慣例允許），執行受影響測試與 `npm run build`；以桌面及行動瀏覽器確認背景可清楚察覺但不影響文字對比、前景海報比例、輪播控制與 fallback。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認，未獲人類確認前不得實作。
- Task 19 待人工確認範圍：裝飾背景來源目前約為 40% 不透明度，但右側覆蓋層約至 `rgba(16, 17, 20, .9)`，導致 artwork 仍然過暗。應將背景提高至約 55%，並將右側覆蓋層降至約 30–40%；左側文字區須保留強對比與可讀性。不得改變前景海報的自然／原始比例、`object-fit: contain` 或尺寸限制，也不得改變既有資料來源、輪播、可及性與 fallback 行為。
- Task 19 視覺驗證要求：目前 Playwright 無法作為證據，因 localhost 指向另一個應用程式，遠端目標則為 `ERR_BLOCKED_BY_CLIENT`。實作後必須以指向正確本機或已部署應用程式的有效 Playwright 截圖確認桌面及行動版亮度、左側文字對比與前景海報比例；未取得該截圖前，不得宣告視覺驗證完成。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認，未獲人類確認前不得實作。
- Task 20 待人工確認範圍：前台在讀取每個既有 child cache 時，必須先在該分類內依種子的可解析發佈時間由新至舊排序，之後才套用根首頁每類最多 12 筆的限制，或將該分類序列納入父分類／單一分類頁的聚合結果。父層與首頁仍須嚴格保留既有設定的 child category 順序；排序不得把不同分類的種子互相混排。僅可使用快取 payload 中既有資料，不得呼叫 MTeam API、讀寫資料庫、刷新或改寫快取。
- 發佈時間解析必須支援目前快取資料中已知且合理的時間戳格式，無法解析、缺失或格式不合法的值不得拋出錯誤，均排在已解析時間之後；同一時間或同為不可解析時，必須保留快取原始輸入順序，確保結果穩定。實作前須確認現有 payload 的時間欄位與格式，並採用 Laravel／Carbon 的已安裝版本 API。
- Task 20 測試要求：新增或更新適當的單元／功能測試，分別驗證單一 child cache 的日期遞減排序與不可解析時間安全置後且穩定、首頁每類上限 12 筆在排序後套用，以及父分類聚合仍維持設定的分類順序但每一分類內為日期遞減。測試資料應只寫入／讀取測試快取，並明確斷言頁面請求不觸發 API 或資料庫。實作後執行最窄相關測試、PHP Pint、變更 PHP 檔案語法檢查與差異檢查。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認；在取得人類明確確認前不得實作。
- Task 21 待人工確認範圍：`public/images/bg1.jpg` 與 `public/images/bg2.jpg` 是使用者提供、只供海報卡片使用的本機備援資產。遠端海報 URL 缺失、無效或於瀏覽器載入失敗時，每一張已渲染卡片都必須使用兩者之一；選擇須對該元素穩定，避免在 `onerror` 每次觸發時重新隨機而造成無效或不一致的動態行為。伺服器輸出應提供安全的 asset／URL 路徑，client-side `onerror` 必須可靠地改為該卡片預先決定的備援，並避免無限錯誤迴圈。
- Task 21 不得把 `bg1.jpg` 或 `bg2.jpg` 視為可供 Hero 選取的真實海報紀錄，除非使用者另行明確要求；不得呼叫 API、讀寫資料庫、刷新或改寫快取。既有無合格資料的空狀態必須保留其獨立視覺，不得因備援資產而偽裝成有可展示的媒體資料。
- Task 21 驗證要求：新增或更新適當的功能標記／單元測試，涵蓋缺失海報的穩定備援選取、安全 asset 路徑輸出，以及遠端圖片載入失敗的 client fallback 行為；執行最窄相關測試及 `npm run build`。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認；在取得人類明確確認前不得實作。
- Task 22 已取得人工釐清並進入實作：重排既有設定驅動的 child category 順序，使所有以該設定衍生的顯示、情境式二級導覽、首頁聚合、父類頁聚合及 `mteam:refresh-category` 無參數刷新順序一致。電影順序為：`419` 高清、`421` 蓝光、`439` Remux、`420` DVD、`401` 标清；電視劇順序為：`402` 高清、`438` 蓝光、`404` 教育纪录、`435` DVD、`403` 标清；成人 children 依序為：`429`、`430`、`432`、`426`、`431`、`410`、`424`、`437`、`412`。
- 成人 child 的可見標籤僅移除「成人 」前綴，成人父層「成人」標籤不變。精確標籤為：`429` 高清无码、`430` 标清无码、`432` 蓝光无码、`426` DVD无码、`431` 蓝光有码、`410` 高清有码、`424`／`437` 标清有码、`412` 动画。
- Task 23 已實作：Hero Swiper 同張海報的裝飾背景由 `opacity-55` 提高至 `opacity-70`，右側覆蓋層由 `.55/.35` 調整為 `.35/.20`，以取得明顯更亮的視覺平衡；左側文字區的既有高對比覆蓋層維持不變。
- Task 23 的前景海報自然／原始比例、`object-fit: contain` 與尺寸限制均未變更；裝飾背景仍不可互動並使用 `alt=""` 裝飾語意。既有資料來源、輪播、導覽／分頁、鍵盤與焦點／hover autoplay 行為，以及 0、1、少於 10 筆有效海報時的 fallback 均維持不變。已新增伺服器輸出回歸斷言，待獨立技術審查。
- Task 23 尚無有效的視覺瀏覽器驗證：正確本機或已部署應用程式目標不可用，因此未取得桌面及行動版亮度、左側文字對比與前景海報比例的有效驗證。不得宣告視覺瀏覽器驗證完成。Review Status、Human Review 與 Reviewed By 維持 Pending／未確認。
- Task 22 不得改變任何 category ID、既有 slug、具名路由、路徑、cache key、TTL、刷新 payload、成人 `mode: adult` 行為、raw `category` 精確隔離、成功且非空才寫入與失敗／空結果保留既有快取的語意。電影、電視劇、動畫及遊戲等非成人分類的可見標籤與既有行為不得受此調整影響；成人父層標籤亦不得變更。
- Task 22 測試要求：新增或更新測試以斷言設定 child ID 的完整順序、各父類及首頁聚合／情境式導覽輸出的順序、所有既有 child routes 仍可存取，以及成人 child 顯示標籤移除前綴但父層維持「成人」。測試須特別確認非成人分類標籤及行為未受影響，並確認無參數刷新採用更新後的完整設定順序。Human Review、Review Status 與 Reviewed By 必須維持 Pending／未確認，待獨立審查。
- Task 22 已更新設定與回歸測試，覆蓋完整設定／首頁聚合 ID 順序、電影父頁聚合、三個情境式導覽的可見順序、成人重複標籤 child 的獨立路由，以及無參數刷新順序。`MovieTorrentCacheTest`、`MoviePreviewTest` 與 `RefreshMteamCategoryCommandTest` 共 42 tests／345 assertions 通過；Pint、變更 PHP 檔案語法與 `git diff --check` 均通過。Human Review、Review Status 與 Reviewed By 維持 Pending／未確認，待獨立審查。
- Task 24 待人工確認範圍：Hero 的伺服器端候選資料仍只能取自目前只讀快取中的有效絕對 HTTP(S) 海報 URL；缺失、無效或非 HTTP(S) 海報的紀錄必須在伺服器端排除，不得產生 Hero slide。`public/images/bg1.jpg` 與 `public/images/bg2.jpg` 僅為卡片備援，Hero 不得使用、替換或偽裝為這些本機預設圖片。
- 瀏覽器端必須處理前景主海報的實際圖片載入錯誤：當該 `<img>` 觸發 error 時，安全地移除其對應 Hero slide，避免空白／壞圖紀錄繼續停留於 Swiper。每次移除後須依 Swiper 已安裝版本及既有初始化慣例安全更新 slides、目前 active index、導覽按鈕、pagination 與 autoplay；不得留下失效控制項、索引越界、重複計時器或 JavaScript 例外。裝飾背景若沿用同一海報，也不得使其失敗阻止前景錯誤處理或留下空白投影片。
- 0／1／多筆錯誤行為：原本沒有合格候選資料時，直接呈現既有 Hero 漸層 fallback，不初始化無內容的 Swiper；只有 1 張可載入海報時，不顯示或啟用不必要的導覽、分頁與 autoplay；多張時，任一張載入失敗後保留其他正常 slide 並正確重算控制項及 autoplay；若最後一張也失敗，須銷毀或停用 Swiper 的互動／autoplay，移除輪播控制項，並顯示既有 Hero 漸層 fallback。不得將卡片備援圖用於此全失敗狀態。
- 視覺調整範圍：Hero Swiper 外層、Swiper container 與前景主海報均移除 rounded corners／圓角 class；卡片不在此任務範圍。現有 border、shadow、可見焦點、替代文字／裝飾圖片語意、鍵盤與 hover／focus autoplay 行為必須保留，除非在無可用 slide 時為安全停用輪播所必需。
- Task 24 測試與驗證：新增或更新可行的伺服器輸出回歸測試，確認無效海報不產生 Hero slide、Hero 不引用 `bg1.jpg`／`bg2.jpg`、圓角已自指定 Hero 元素移除，以及 0／1／多筆候選的初始控制項語意。以現有前端測試能力驗證圖片 error 後的單張移除、多張重算與全失敗 fallback，並執行相關測試與 `npm run build`。正確的本機或已部署瀏覽器目標目前仍不可用（先前 localhost 指向其他應用程式，遠端目標受 `ERR_BLOCKED_BY_CLIENT` 阻擋），因此不得聲稱已完成桌面／行動的實際圖片錯誤或視覺驗證；待取得指向本修訂的有效瀏覽器目標後再驗證。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認，未獲明確人類確認前不得實作。
- Task 25 待人工確認範圍：使用者釐清所有海報封面都不得使用圓角。Task 24 僅移除 Hero Swiper 外層、container 與前景主海報的圓角；一般海報卡片的摘要外層及其圖片仍保有 `rounded-lg`。本項僅移除一般海報卡片外層與圖片的圓角 class，讓所有卡片封面改為直角呈現。
- Task 25 必須保留海報卡片既有 hover／鍵盤 focus 互動、完整描述 overlay、shadow、尺寸、間距、響應式 grid／版面、圖片失敗備援與無障礙語意；不得變更 Hero、分類主／次級導覽、路由、分類設定、快取讀取／寫入、刷新指令或排程。測試與驗證須更新適當的伺服器輸出標記回歸測試，以確認卡片摘要外層及圖片不再輸出圓角 class，並執行最窄相關測試與 `npm run build`。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認，未獲明確人類確認前不得實作。
- Task 25 已移除一般卡片 `<summary>` 的 `rounded-lg`；其圖片 overflow 容器原本即未使用圓角 class，維持直角。聚焦頁面輸出測試已鎖定卡片 summary／圖片容器及具 `data-fallback-poster` 的一般卡片圖片 class contract，確認三者均不輸出 `rounded*` class，並保留 hover／focus overlay 行為。Hero、分類導覽與快取未變更；Review Status、Human Review 與 Reviewed By 維持 Pending／未確認，待驗證及獨立審查。
- Task 26 待人工確認範圍：`mteam:refresh-category` 必須接受零、一個或多個位置參數分類 ID。零個 ID 時，維持目前從設定取得完整 child category ID 清單並依設定順序逐一刷新；一個或多個 ID 時，只刷新使用者提供的 ID，並嚴格保留首次出現的提供順序。重複 ID 必須在執行前去重，例如 `401 419 401` 僅依序刷新 `401`、`419` 各一次。
- 所有提供的 ID 必須在進行任何 API 呼叫、快取寫入或其他刷新副作用前完成驗證：每個值均須為數字分類 ID，且必須存在於目前已設定的 child category ID 集合。只要任一值格式無效、非數字或未設定，指令必須輸出明確錯誤、回傳失敗狀態，且不得刷新或改寫任何分類快取；不得先刷新有效 ID 再拒絕後續無效 ID。
- 通過預先驗證後，單一分類的 API／服務失敗、空或不相符結果，仍須沿用既有保護：保留該分類既有快取、記錄該分類失敗並繼續處理後續已驗證 ID。若任一分類失敗，指令最終必須回傳失敗狀態，即使其他分類成功；成功分類仍可正常更新。既有成功／失敗／種子數摘要應反映實際去重後處理的分類。
- Task 26 不得變更無參數排程語意：既有每 15 分鐘、名稱 `mteam:refresh-categories`、30 分鐘 `withoutOverlapping` 鎖與無參數全量設定順序刷新均維持不變。不得變更 category cache key、TTL、成人 `mode: adult` 設定導向行為、`categories: [requested category ID]` payload、raw `category` 精確隔離，或成功且非空才寫入與失敗／空結果保留既有快取的保護。
- Task 26 測試與文件要求：新增或更新 Artisan 指令測試，涵蓋零參數完整設定順序、一個 ID、多個 ID 的提供順序、重複 ID 的首次順序去重、無效格式與未設定 ID 在零副作用下遭拒絕，以及中途個別失敗仍處理後續 ID、保留舊快取並以失敗狀態結束。更新本計畫中相關指令使用與行為說明，使其明確記錄多 ID 語意；視現有專案文件慣例同步更新適用的使用者文件。實作後執行最窄相關 PHPUnit 測試、Pint、變更 PHP 檔案語法檢查與差異檢查。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認；未獲明確人類確認前不得實作。
- Task 26 已獲核准並進入實作：CLI 形式為 `mteam:refresh-category`（依設定順序刷新全部）或 `mteam:refresh-category 401 419 412`（只依提供順序刷新指定 ID）。重複指定的 ID 僅使用首次出現；所有指定 ID 須先完成數字與設定驗證，任何無效輸入均在零刷新副作用下失敗。Human Review、Review Status 與 Reviewed By 維持 Pending／未確認。
- Task 26 審查修正：`MovieTorrentCache` 僅在首次實際讀取或寫入快取時才解析 `mteam_movies` store，因此指令可先完成多 ID 預先驗證；新增回歸測試確認 `401 not-a-category` 被拒絕時不呼叫 MTeam 搜尋，也不解析快取 store。指令的 variadic `categoryIds` 參數已補上中文 help 說明。Human Review、Review Status 與 Reviewed By 維持 Pending／未確認。
- Task 27 待人工確認範圍：僅調整頁首成人主導覽連結的視覺樣式。其非 active 預設色與滑鼠 hover 色必須刻意低調、與 header 背景融合，不應以高對比色、hover 揭示或 tooltip 搶占一般導覽視覺；但必須保留原生連結語意、既有可點擊目標與可存取名稱，不能以隱藏文字、`aria-hidden`、移除 href、停用 pointer events 或其他方式降低可操作性。
- 成人主連結僅在鍵盤 `:focus-visible` 時提供清楚的 outline，且不得藉由改變文字色揭示連結；成人父頁 `/adult` 與任一成人 child 頁的主導覽 active state 可依既有 active-state 慣例維持可見，且現有 `aria-current="page"`／情境式次級導覽 active state 不得弱化。電影、電視劇、動畫、遊戲與首頁的既有導覽視覺及行為不在本項變更範圍。
- Task 27 不得變更具名路由、路徑、成人／其他分類設定、快取 key、快取讀寫、MTeam API 請求、刷新指令、排程或頁面資料來源。僅可在既有 Blade／樣式結構中作最小必要變更。
- Task 27 測試與驗證：新增或更新與現有 `MoviePreviewTest` 慣例一致的 Blade 標記／class contract 測試，至少斷言成人非 active 主連結輸出一致的低調預設／hover 樣式、不輸出 tooltip 或 hover 文字色揭示樣式，並輸出不改變文字色的 `focus-visible` outline；另斷言成人父頁與 child 頁 active 主連結依既有 active-state 慣例保留明確 active class 與 `aria-current="page"`。執行最窄相關測試與前端 build；如修改 PHP，另執行 Pint 與語法檢查。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認，未獲明確人類確認前不得實作。
- Task 28 待人工確認範圍：使用者明確要求 production 部署只更新 Git 程式碼，不在伺服器執行 npm build。因此僅移除 `.gitignore` 中使 `public/build` 無法被追蹤的規則，並在成功執行 `npm run build` 後，將 `public/build/manifest.json` 及該次 Vite 產生的全部 production artifacts 納入 Git。不得變更 Vite 設定、entrypoints、bundling 策略、輸出架構或應用程式載入機制。
- Task 28 排除與驗證要求：不得追蹤 `public/hot`、`node_modules`、暫存／開發檔案或非當次生產建置輸出；既有已修改的 README 不在本任務範圍，除非現有部署說明確有缺口且需最小補充。完成後以全新 `git status` 確認 manifest 與全部目前 `public/build` assets 皆被追蹤、排除項目仍未追蹤，並確認伺服器端 Vite 載入仍透過 production build manifest，沒有 bundle 架構變更。Review Status、Human Review 與 Reviewed By 必須維持 Pending／未確認；未獲明確人類確認前不得實作。
