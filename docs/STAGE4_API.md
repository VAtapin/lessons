# Этап 4: библиотека блоков и версионная медиатека

Контракт реализации в существующем движке. Новые уроки и новые типы блоков сюда не входят.

## Общие правила

- Владение определяется текущей серверной GuestIdentity, не body/query. Все write endpoints имеют CSRF, session lock и studio-write throttle; чужие объекты — 404. Чтение своей библиотеки не даёт права публичной публикации.
- JSON документа/блока остаётся строгим: media содержит только `{assetId,versionId}`. Разрешённый URL добавляется application projection в `resources: {image: string}`; его нельзя сохранить как authored JSON. Runtime URL выдаётся только для соответствующего audience.
- Метаданные: `title` (1–200 символов), `tags` (до 20 уникальных строк по 50 символов), `author`, `source`, `rightsBasis`, `usageRights`. Основание прав: `self_created`, `permission`, `public_domain`, `licensed`, `ai_generated`. Автор, источник/описание происхождения и условия использования обязательны; эта запись не является юридической проверкой прав.
- `expectedRevision` — строгий integer; conflict 409 сохраняет локальные правки. Архив обратим и скрывает новый выбор; существующие экземпляры и ссылки работают. Физическое удаление/автоматическая очистка не входят в этап.

## Заготовки

Persistent `BlockTemplateRecord` (owner, metadata, current version, revision, archived) и immutable `BlockTemplateVersion` (block JSON, locales/defaultLocale, attribution). Использовать существующий Domain BlockTemplate для независимой вставки; имя модели не заменяет доменный DTO.

| Endpoint | Тело / ответ |
| --- | --- |
| GET `/api/studio/templates?q=&tag=&type=&locale=&archived=0|1` | `{templates:[{id,title,tags,type,locales,revision,currentVersionId,archived}]}` |
| POST `/api/studio/templates` | `{lessonId,expectedLessonRevision,blockId,...metadata}` → `{template: detail}`, 201; сервер копирует сохранённый owned block со всеми переводами, UI требует явного сохранения текущих правок |
| GET `/api/studio/templates/{id}` | `{template: detail}`; detail включает metadata, `versions` и `usages` |
| PUT `/api/studio/templates/{id}` | `{expectedRevision,locales,defaultLocale,block,...metadata}` → `{template: detail}`; новая immutable version, проверка BlockInstance/owner-aware media |
| POST `/api/studio/templates/{id}/versions/{versionId}/instantiate` | `{locales:[...]}` → `{block}`; свежий UUID/точный origin, явно выбранные локали должны быть subset исходных, отсутствующий перевод — 422 |
| POST `/api/studio/templates/{id}/archive` | `{expectedRevision,archived:boolean}` → `{template: detail}` |

Версия detail: `{id,versionNo,locales,defaultLocale,block,attribution,createdAt}`. Usage: `{lessonId,lessonVersionId,title,status,blockId}`; только свои материалы. Usage вычисляется/фиксируется при сохранении draft; released сохраняет ссылки. Неизвестный legacy origin не даёт доступа и не считается подтверждённой ссылкой. При вставке архивного источника — 409; уже сохранённый экземпляр независим от архива/изменения заготовки.

## Медиа

Persistent `MediaAsset` (owner/metadata/revision/archive/currentVersion) + immutable `MediaVersion` (UUID, private storage key, MIME/bytes/dimensions/SHA256/attribution). Файл вне public, без storage:link; generated UUID определяет путь. Проверять настоящий PNG/JPEG/WebP по MIME/декодируемым заголовкам/размерам, не только расширение. SVG/HTML запрещены. Configurable pixel/dimension bound предотвращает чрезмерный размер декодирования.

| Endpoint | Тело / ответ |
| --- | --- |
| GET `/api/studio/media?q=&tag=&archived=0|1` | `{media:[...version summaries],assets:[...asset summaries],quota:{usedBytes,limitBytes,maxFileBytes}}`; builtin плюс свои версии, reference picker проверяет archive |
| POST `/api/studio/media` | multipart `file`, metadata (`tags` JSON array) → `{asset: detail}`, 201 |
| GET `/api/studio/media/{id}` | `{asset: detail}`; versions/usages |
| PUT `/api/studio/media/{id}` | JSON `{expectedRevision,...metadata}` → `{asset: detail}`; file content не меняется |
| POST `/api/studio/media/{id}/versions` | multipart `file`, `expectedRevision` → `{asset: detail}`; новые bytes/UUID, immutable старый файл и attribution |
| POST `/api/studio/media/{id}/archive` | `{expectedRevision,archived:boolean}` → `{asset: detail}` |

Private version summary: `{assetId,versionId,versionNo,title,url,mime,bytes,width,height,archived}`. Совместимый builtin summary: `{assetId,versionId,url,labelKey}`. Detail asset: `{id,title,tags,author,source,rightsBasis,usageRights,revision,currentVersionId,archived,versions,usages}`; версии также содержат immutable attribution/SHA256/createdAt. Usage: owned lesson/version/block и template/version, без чужих titles или приватных paths.

Квоты в бинарных единицах: максимум файла **20 × 1024² байт**, гость **100 × 1024²**, будущий аккаунт **1024³**. Сейчас регистрация отсутствует: действует guest quota. Все сохранённые версии, включая архивные, учитываются. Server row lock/reservation владельца предотвращает конкурентное превышение; failure откатывает reservation и удаляет только созданный этой попыткой файл. Configurable quota не доверяется клиенту.

## Выдача файлов и интеграция

- GET `/media/owned/{assetId}/{versionId}` — только владелец exact pair, включая старые/архивные версии.
- GET `/media/participation/{sessionId}/{assetId}/{versionId}` — только реальный cookie-участник этого занятия, exact reference в текущем этапе immutable lesson snapshot.
- GET `/media/projection/{token}/{assetId}/{versionId}` — только действующий projector token и reference в текущем этапе. Смена этапа отменяет доступ к файлам, которых нет в новом этапе; неизвестная/чужая/future reference — 404.
- Ответы файлов: корректный Content-Type, nosniff, private/no-store; не раскрывать disk paths или исходное имя. Builtins остаются общими и выдаются через существующий version manifest.
- MediaCatalogue расширяется, а не заменяется вторым каталогом. `assertDocument` / `assertBlock` используют owner context; `resolve` проверяет exact pair/реальный файл. Private runtime projection выполняется после domain audience projection.
- Studio validation и template validation допускают свои доступные старые версии, чтобы existing references сохранялись; picker скрывает архивные из нового выбора. Usage вычисляется из текущих сохранённых owned snapshots и проверенных exact references: нет второго изменяемого индекса. Released snapshots сохраняют старые ссылки. Editor объединяет active/archive media lists для отображения уже выбранной архивной версии.
- GD полностью декодирует изображение после проверки bytes/MIME/pixels/dimensions; исходные bytes не преобразуются. Требуется PHP ext-gd. Подтверждённый local/production memory_limit — 512M; pixel/dimension limits настраиваются вместе с доступной памятью. Настройки env перечислены в .env.example. Database backup не включает private files: они требуют отдельного резервирования.
- Встроенный manifest содержит 18 просмотренных иллюстраций UI-Design/1–6,11–22 и одну новую сцену взаимопомощи. Исходники не перезаписаны; макеты страниц и logo не предлагаются как изображения урока. Названия переводимы, ссылки pin asset/version.

## Приёмка

Тестировать foreign ownership/CSRF, MIME/content/размер/pixel limits, rollback quota/file failure, immutable replace, archive/restore, independent insert/full translations/incompatible locale, revision conflict, pinned released session, usage, public active-stage-only image access и отсутствие storage URL/notes/solution leaks. UI RU/DE: upload → pick → save/reload → template → две независимые вставки → runtime с private image. Проверить 360 px и approved paper/watercolor стиль.
