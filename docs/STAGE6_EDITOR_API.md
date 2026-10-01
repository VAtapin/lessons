# Этап 6B: полноценный конструктор и рабочие переводы

Контракт реализации дополняет [6A](STAGE6_API.md), [BLOCK_CONTRACT](BLOCK_CONTRACT.md) и [интерактивность](STAGE5_API.md), основан на ТЗ §10, §15 и §24.6. Подтверждённые проверки и установленная версия production записаны отдельно в [PROJECT_STATUS](../PROJECT_STATUS.md); наличие API-контракта само по себе не означает завершённую приёмку.

## Граница рабочего черновика и строгого содержания

`LessonDocument`, зарегистрированные `BlockType`/`InteractiveBlockType`, released snapshots и rehearsal/runtime остаются строгими. Рабочий `EditorDocument` имеет знакомую форму `{id,schemaVersion,defaultLocale,locales,content,stages}` и те же block/stage fields, но обязательные **переводимые строки** могут быть пустыми. Это private authoring DTO, не runtime document.

- Additive `lesson_versions.editor_draft` — nullable JSON; null означает прежний strict-only документ. Non-null хранит полный реально введённый рабочий документ со всеми declared locales, включая blank/partial strings; document.id в обоих представлениях назначается сервером по version ID.
- `lesson_versions.document` остаётся strict JSON. У authoring draft это последний strict baseline, который может быть старее рабочего черновика; он не источник актуального preview/release/rehearsal/library snapshot. Если текущий working document полностью ready, baseline допустимо обновить actual strict документом; если есть partial locales, baseline сохраняется. Никакие искусственные структурные placeholders в нём не сохраняются.
- POST `/api/studio/lessons` остаётся существующим strict bootstrap настоящего стартового `newDocument` с видимыми редактируемыми начальными текстами. Он не принимает partial first-create и не создаёт материал с невидимыми фиктивными строками. После создания PUT с saveId может сохранить и полностью blank default locale, и zero ready locales.
- Это намеренная минимальная совместимая граница: сейчас document NOT NULL. Если понадобится first-create полностью пустого материала без начального документа, отдельно согласовать nullable document только для authoring draft + editor_draft и все соответствующие readers; не обходить NOT NULL synthetic content.
- Released version хранит immutable strict snapshot выбранных ready locales и immutable полный private editor_draft, если он есть. Правка released material создаёт новый draft с новым version ID, не меняет старые editor_draft/document. Internal rehearsal snapshot содержит только strict document, editor_draft=null и purpose=rehearsal.
- Содержание занятия всегда читается из session.lesson_version_id → strict document. Editor draft не отдаётся по projector/student/media bearer routes, не включается в command receipts и не привязывается к уже начатому runtime.

## EditorDraft validation: разрешены неполные тексты, не неполная структура

Предлагаемый pure-domain `EditorDraft::fromArray(array $data, BlockRegistry $registry):self` использует **тот же registry и validators**. В Domain нет Laravel imports. Методы `toArray()`, `readiness()`, `readyDocument(array $locales):LessonDocument`, `readyBlock(string $blockId,array $locales):BlockInstance`, `projectStage(Audience $audience,string $locale,string $stageId):array` не возвращают внутреннюю validation copy.

1. Известные ID/type/schemaVersion, root/stage/block shape, непустые stages/blocks, уникальность stage IDs и block IDs across stages, locale syntax/default membership, enums/numbers, config, media slots, solution и origin остаются строгими. Block/option/item/role IDs case-sensitive; их наборы согласованы между всеми locales. Никаких произвольных HTML/widget/config fields.
2. Каждый declared locale присутствует в root/stage/block content. Required текстовые keys присутствуют и имеют string type, включая пустую строку; отсутствующий locale/object/required key не превращается сервером в новый текст. UI при добавлении locale создаёт явные blank text fields и копирует только structural IDs. Optional strings/teacherNotes подчиняются прежним allowed keys/limits; отсутствие optional fields не мешает readiness.
3. Blank относится только к зарегистрированным переводимым textual leaves: material/stage title, block text/question/alt и option/item/role labels. Optional notes/caption/title/source разрешают blank уже сейчас. Числа, ID, solution references, media IDs, capacities и locale names нельзя объявить «неполным переводом». Nonblank текст не trim/normalize; прежние Unicode/length ограничения сохраняются.
4. Для reuse строгого normalizer допустима isolated validation copy: заменить только известные blank required text leaves на внутренние nonblank values, вызвать прежние domain validators и восстановить **точные исходные strings по конкретным paths** в normalized editor output. Для непустой whitespace-only строки замена сохраняет исходную Unicode length и не уменьшает byte length: например, заменяет один whitespace code point на nonblank code point той же UTF-8 ширины. Только исходная пустая строка получает один внутренний символ. Замена всего длинного blank значения короткой строкой запрещена: она обошла бы прежний maximum length. Copy никогда не сохраняется/не отдаётся; нельзя восстанавливать global string replacement. Даже пользовательская строка, совпавшая с внутренним значением, не изменяется.
5. Text paths/required flags берутся из небольшого optional metadata interface зарегистрированного definition: `EditorTextFields::translatedTextFields():array` с descriptors `{path,required,blankMode}`, где path — list<string>, `*` обозначает list item (`['options','*','text']` для labels), required — bool, blankMode — `trim` или `unicode`. `trim` совпадает с существующим Shape::text; `unicode` — с Shape::boundedText и его Unicode whitespace predicate. Root/stage paths фиксирует EditorDraft. Это описание текстовых leaves, не второй type catalogue и не новый engine validation; bounds/enums/IDs/solutions проверяет существующий normalizer. Новый тип без такого metadata имеет только strict editor path до его явного расширения.
6. Application проверяет **все actual media references** рабочего документа под owner, включая references из partial locale/block. Не разрешается сохранять чужие/неизвестные asset/version, неизвестную solution или недопустимую config под видом draft. Temporarily invalid numeric/config edits остаются только локальными и показывают invalid save state.

Required nonblank predicate совпадает с конкретным strict definition. Пустота не определяется качеством/языком текста: введённый непустой перевод не обязан быть литературно законченным, и сервер не имитирует редакторскую оценку.

## Readiness DTO

Readiness вычисляется из authoritative working document сервером, не присылается как доверенная отметка клиента:

```json
{
  "defaultLocale": "ru",
  "readyLocales": ["ru"],
  "locales": [
    {"locale":"ru","status":"ready","issues":[]},
    {"locale":"de","status":"partial","issues":[
      {"code":"required_text","path":"/stages/0/blocks/1/content/de/question","stageId":"s1","blockId":"b2","locale":"de"}
    ]}
  ]
}
```

Statuses относятся к переводу **всего материала**: draft — все required textual leaves пусты; partial — хотя бы одна заполнена, хотя бы одна отсутствует как blank; ready — все обязательные тексты заполнены и actual strict projection этого locale проходит существующую validation. Optional empty notes/caption/source не понижают статус. Структурный/media defect — 422, не статус partial. `readyLocales` сохраняет порядок declared locales. Block readiness вычисляется тем же правилом отдельно для library extraction.

Issue DTO `{code,path,locale?,stageId?,blockId?}` содержит machine code и RFC6901 JSON Pointer к фактическому editor document; array indexes помогают адресовать даже duplicate/invalid IDs. UI локализует code и переводит фокус к указанному stage/block/field. Не возвращать raw SQL/exception strings, значения пользовательских текстов, owner keys или internal validation copy.

Private EditorLesson DTO сохраняет прежние keys и добавляет readiness:

`{id,revision,status,versionId,document:EditorDocument,readiness}`.

GET lesson, новый save и release возвращают `{lesson:EditorLesson}` (save также receipt fields ниже). Для editor_draft=null document — strict stored document; для non-null — полный actual working draft, включая private notes/solution, только владельцу. List title берётся из working defaultLocale, может быть пустым; UI показывает localized unnamed label отдельно, не сохраняет его как авторский текст.

## HTTP save и UUID receipt

PUT `/api/studio/lessons/{id}` с editor protocol принимает строго:

```json
{
  "saveId":"10c553fa-17f3-4c32-a861-2ec2d50e1ef5",
  "expectedRevision":7,
  "document":{
    "id":"ff136de3-af7e-43b2-8b82-bf45fbdab6f4",
    "schemaVersion":1,"defaultLocale":"ru","locales":["ru","de"],
    "content":{"ru":{"title":"Урок"},"de":{"title":""}},
    "stages":[{
      "id":"s1","content":{"ru":{"title":"Этап"},"de":{"title":""}},
      "blocks":[{
        "id":"b1","type":"core.text","schemaVersion":2,
        "content":{"ru":{"title":"","text":"Материал","source":""},"de":{"title":"","text":"","source":""}},
        "config":{"presentation":"paragraphs"}
      }]
    }]
  }
}
```

Пример показывает готовый RU и сохранённый blank DE. SaveId — UUID, canonical lowercase; expectedRevision — integer ≥1. Unknown envelope fields отвергаются в новом protocol. Server-generated identity в сохранённом результате может отличаться от исходного document.id при fork released → new draft.

Additive table `lesson_save_receipts`: id, lesson_material_id FK, save_id UUID, fingerprint SHA256, applied_revision positive integer, applied_version_id UUID, created_at. Unique `(lesson_material_id,save_id)`; UUID lower/upper replay одинаков. Receipt не содержит полный document/PII/owner credential, не является grant. FK version binding должен сохранять receipt при обычном release/new draft; receipt нельзя отдать чужому владельцу.

Fingerprint — canonical JSON `{expectedRevision,document}` **исходного принятого тела**, до defaults, server ID rewrite, whitespace changes и ready-locale filtering. Canonicalization сортирует object keys, сохраняет list order и строковые code points/whitespace; различает JSON object/list и integer/float. SaveId сам не часть fingerprint. Нельзя хэшировать нормализованный strict baseline: разные реальные draft edits обязаны различаться.

Одна transaction: OwnerMutation(owner) → owned material row lock → receipt lookup → revision check → EditorDraft/domain/media validation → fork/update version → revision +1 → receipt insert. Successful новый UUID увеличивает revision ровно на 1, даже если тело совпадает с прошлым документом. Ошибка не создаёт receipt, не меняет document/editor_draft/current_version_id/revision. Source owner tombstone/session revocation проверяются как в 6A.

| Ситуация | HTTP / envelope |
| --- | --- |
| Новый saveId, revision совпадает | 200 `{lesson,acknowledgedSaveId,appliedRevision,appliedVersionId}` |
| Тот же UUID + тот же fingerprint | 200 то же acknowledgment/appliedRevision/appliedVersionId, **fresh current** lesson, без повторной записи |
| Тот же UUID + другое тело/revision | 409 `{error:{code:"save_conflict"},lesson:current}`; не менять ранее принятое тело |
| Новый UUID + stale expectedRevision | 409 `{error:{code:"revision_conflict"},lesson:current}` |
| Небезопасный/невалидный editor shape/media | 422 `{error:{code:"invalid_editor_document"},issues:[Issue]}` |
| Старый PUT без saveId и current editor_draft=null | Прежний strict save path/ответ, прежняя optimistic revision semantics |
| Старый PUT без saveId и current editor_draft non-null | 409 `{error:{code:"editor_update_required"},lesson:current}`; не терять рабочие locales/strings |

Receipt replay ищется до expectedRevision/validation: acknowledged body может относиться к прежней версии/редакции. Если fresh lesson.revision **не равна appliedRevision**, client фиксирует conflict, не подменяет local draft fresh document и не продолжает queued autosave автоматически. Ack подтверждает прежнюю запись, а не сохранённость текущего экрана. Новые save attempts нельзя строить на appliedRevision старого receipt, когда current уже ушёл вперёд.

Technical receipt window — 30 дней от created_at, согласованно с 6A. После cleanup старый successful body имеет stale expectedRevision и получает conflict, не применяется ещё раз. Client не переиспользует UUID с другим телом, не обещает exact receipt replay после окна.

## Current-ready resolver: никакого stale baseline

Один application `CurrentDraftResolver` централизует получение working version (`editor_draft ?? document`), validation/readiness и actual ready subset. Его используют release, start, rehearsal, ownedBlockSnapshot и saved preview; читающий document напрямую старый путь считается integration defect.

- Для material snapshot defaultLocale обязан быть ready. Выбранный язык проведения тоже обязан быть ready; нельзя скрыто брать RU вместо partial DE. Runtime.start откатывает transaction целиком при недоступном selected locale, включая implicit release.
- POST release принимает `{expectedRevision,locales?}`; omitted locales означает все readyLocales. Explicit locales — nonempty unique subset declared/ready locales, включающий рабочий defaultLocale. Нельзя менять default через release body. Прежний `{expectedRevision}` совместим, но при non-null editor_draft resolver читает working draft.
- Resolver строит actual strict LessonDocument только выбранных ready locales: фильтрует root/stage/block translations и teacherNotes, сохраняя ID/config/media/solution/origin/order. Затем проходит **реальную** strict validation и owner-aware media validation без каких-либо substituted strings.
- RU ready + DE partial → strict released document.locales=['ru']; private editor_draft продолжает содержать DE partial. Старт/проекторы/version advertised locales используют strict snapshot, UI показывает, какие working translations готовы и какие включены в release. Нельзя объявить DE доступным по одному declared locale.
- No ready default → 422 `{error:{code:"translation_not_ready"},readiness,issues}`. Rehearsal выбранного ready locale также требует готовый working default. UI предлагает закончить default или явно поменять его в редакторе/сохранить; не подменяет его на сервере.
- Новая rehearsal использует actual ready subset текущего saved revision; она не выпускает authoring draft. Old rehearsal/released/run snapshots остаются неизменными после autosave. Release уже released version идемпотентен только для её существующего immutable snapshot; изменить subset можно через новый saved draft.
- Library extraction preferred boundary: saved **block** и его default locale готовы независимо от blank material/stage title или других partial blocks. `ownedBlockSnapshot` берёт current working block, его ready locale subset и actual strict BlockInstance через тот же resolver; source revision/media ownership остаются обязательны. Source material.defaultLocale должен быть ready для самого блока. Никогда не извлекать старый baseline block. Если implementation сначала поддерживает только whole-material-ready extraction, это явный ограниченный fallback с disabled action/translation_not_ready, а не обещание сохранения любого готового блока.
- Metadata/history versions endpoint `version.document` сохраняет strict snapshot semantics; при необходимости readonly private selector дополнительно возвращает `editorDocument`/readiness отдельными fields. Current EditorLesson.document — рабочий DTO. Эти поля не смешивать в UI и runtime readers.

Computed media и template usages должны читать **editor_draft как authoritative content для редактируемого authoring draft**, document для strict-only/released/rehearsal versions. Existing usage DTO сохраняет kind='lesson' и получает `contentSource:'editor'|'snapshot'`: editable draft с editor_draft → editor; actual immutable strict reference → snapshot. У released версии private working references тоже сохранены: editor-only reference показывается с contentSource=editor, без утверждения, что она присутствует в released strict snapshot. Exact owned media/template origin pairs и case-sensitive block IDs проверяются как раньше. Одинаковую пару version/block/reference в strict/editor не дублировать: для immutable версии предпочесть snapshot marker; distinct usages реально разных сохранённых versions остаются. Stale strict baseline редактируемого draft не считается местом использования. Backup включает новый JSON/table обычным DB backup, не требует копировать immutable media files при каждом autosave.

## Autosave state machine клиента

- Состояния UI: loading, clean, dirty, saving, invalid, offline, retry-required, conflict. Saved/clean показывается только после ack текущей local generation; readiness draft/partial/ready — отдельное состояние содержания, не network success.
- Debounce 800 ms после последней завершённой правки; IME composition не разбивать промежуточным save. Manual Save flushes debounce. Один flight на lesson/editor instance; новая правка во время save меняет local draft и ставит последнюю generation в очередь, не меняет pending body.
- Перед отправкой clone complete working draft + expectedRevision + новый saveId. Эта frozen copy не мутируется при undo, изменении locale, copy/delete, последующей typing или получении server response. Не использовать текущий reactive object для retry.
- Ack с current revision=appliedRevision обновляет server revision/version identity. Если local generation не изменилась, можно принять normalized editor response/clean. Если изменилась, сохранять local edits, аккуратно обновить document.id на acknowledged version ID и отправить последнюю queued generation с новой expectedRevision/new UUID.
- Network failure/timeout с неизвестным результатом оставляет frozen pending request и local draft. Manual Retry отправляет **тот же** UUID/body; нельзя сгенерировать новый UUID/expectedRevision для прежнего uncertain save. Автоматический infinite retry не нужен. До reconcile/retry release/rehearsal/start/library snapshot заблокированы.
- 422 подтверждает, что запись не применена: issues показываются, дальнейшая исправленная edit может создать новый UUID при прежней server revision. 409 сохраняет local draft и actual current state отдельно: пользователь явно выбирает reload/discard либо открывает свои edits для ручного переноса; без force overwrite/автоматического merge разных stages/locales.
- Offline сохраняет in-memory draft и undo/pending state в текущей вкладке, без заявления о server persistence. Reload/close может потерять несохранённое: показывать beforeunload и honest banner. LocalStorage/IndexedDB storage приватного draft в этот scope не добавляется.
- Auth/guest identity change, revocation, 403/404 останавливают autosave/retry и закрывают доступ к прежнему private editor view до явной загрузки authorized context. Не отправлять frozen draft после нового login/claim автоматически. Owner keys не нужны client для этого; существующий identity-change механизм 6A используется и здесь.
- Autosave сохраняет только рабочий материал. Upload/replace файла, создание template, release и start остаются отдельными подтверждаемыми API actions. Editing после успешного release снова fork нового draft.

## Undo/redo и copy

Undo/redo локальный, максимум **100** законченных editor изменений. Записи содержат working document + editor selection/locale для понятного возврата; server revision/version identity, pending UUID/body и receipt не откатываются. Consecutive typing одного field объединяется (до 500 ms quiet boundary), composition — одно изменение. Copy/add/delete/reorder/layout/locale add-remove — отдельные операции. Новая edit после undo очищает redo.

- Undo уже отправленной правки не отменяет server commit: после ack текущее undo состояние сохраняется следующим ordinary save с новым UUID/revision. Ни delete server version, ни rollback release/runtime/media side effects не вызываются.
- Copy block: fresh crypto UUID block.id, deep copy всех content locales/config/media/solution/teacherNotes/origin, без runtime state/answers. Option/item/role IDs внутри блока **сохраняются** и остаются согласованными across locales/solution/capacities; они scoped by block, не глобальны.
- Copy stage: fresh stage UUID и fresh UUID каждого copied block; полный deep copy translations/notes/layout/duration/order. Legacy IDs исходных элементов не переписываются. Операция не изменяет source template/version и не придумывает новый origin.
- Delete сохраняет минимум один stage и один block per stage; пользователь видит затрагиваемый элемент. Reorder доступен кнопками и клавиатурой; drag допустим как дополнительный способ, не единственный. Selection после удаления выбирает соседний реальный элемент.
- Add locale: явно валидный новый content locale, пустые text leaves в root/stages/blocks, structural option/item/role IDs и order из текущей структуры; не копировать RU/DE тексты как будто это перевод. Optional teacherNotes, если map присутствует, получают blank entry. Remove locale удаляет все соответствующие maps, не меняет IDs и ранее созданные snapshots; удалить default/последний locale нельзя без предварительного явного выбора нового default.
- До save draft body проходит structural validation; временный locally invalid editor state может быть undoable, но не сохраняется как частичный перевод. Insert template сохраняет existing immutable origin/fresh block UUID и строгие target locales как в библиотеке; partial material locales не дают права выдумать missing template translations.

## Layouts и три audience preview

Stage.config.layout допускает ровно `vertical`, `two-columns`, `material-above-task`; omitted layout остаётся vertical. Это узкое расширение Stage whitelist, без произвольных CSS/pixel coordinates. Duration/config сохраняют прежнюю validation. Все editor/public/rehearsal/runtime views используют общий StageRenderer/BlockRenderer.

- Vertical — текущая линейная композиция.
- Two-columns — два столбца на достаточной ширине, автоматический row-major flow по исходному blocks order; при узкой ширине один столбец в **том же** порядке. Odd block count допускается. DOM/tab/reading order и order сохранённого JSON не меняются.
- Material-above-task — линейный порядок с визуальным разделением материалов и заданий; text/image — material, prompt/answerable blocks — task. Это presentation metadata известных definitions, не новый каталог. Renderer не группирует/переставляет массив молча: если material размещён после task, он остаётся там, UI предлагает явный reorder. Макет не ограничивает число блоков и не меняет answers/IDs.

Readonly owner POST `/api/studio/lessons/{id}/preview` принимает `{expectedRevision,document,audience,locale,stageId}`, audience teacher/student/projector. Проверяет owned material/revision + EditorDraft structural/media validation, ничего не сохраняет, не создаёт session/participant/receipt и не повышает revision. Response `{preview:{audience,locale,revision,stage,readiness}}`; stage содержит **только выбранный этап** и actual strings данного locale. Local preview тем же renderer может предварительно показывать эти actual strings; server projection — authority для безопасной audience shape.

Blank/partial перевод доступен в private editing preview с readiness notice: пустота остаётся пустотой, UI explanation находится вне авторского content. Preview не использует strict baseline или fallback языка. Projection может reuse existing registered BlockType projection через isolated validated shape, но все substituted required text leaves обязательно восстановлены из working data **до** получения внешнего DTO. Отдельно проверить отсутствие synthetic values и string-collision case.

- Teacher preview содержит selected stage notes/teacherNotes и предусмотренную teacher solution карточку; только owner endpoint/page.
- Student/projector preview не содержит solution/origin/stage notes/teacherNotes/owner credentials, другой locale или ответы людей. PublicResult не раскрывается только от выбора preview mode. Editor preview статичен: не выдаёт teacher command/answer permissions и не создаёт fake persisted results; реальную проверку answer/timer/reveal/moderation выполнять в saved ready rehearsal.
- Private preview media resources используют existing owned exact asset/version URLs; это не public preview token. Не выдавать file URL по arbitrary path, foreign pair или stale baseline reference. Rehearsal продолжает owner-only URL/active-stage правила 6A.
- Все три представления, layouts, empty text и literal HTML должны работать на 360/768/1366 px без изменения logical order и горизонтального overflow.

## Минимальная последовательность реализации и приёмка

### Domain handoff перед implementation

Следующие реализованные signatures относятся к `App\Domain\Lessons` и не имеют HTTP/Laravel dependency:

```php
interface EditorTextFields extends BlockType
{
    /** @return list<array{path:list<string>,required:bool,blankMode:'trim'|'unicode'}> */
    public function translatedTextFields(): array;
}

final readonly class EditorDraft
{
    public static function fromArray(array $data, BlockRegistry $registry): self;
    public function toArray(): array;
    public function readiness(): array;
    public function blockReadiness(string $blockId): array;
    public function readyDocument(array $locales): LessonDocument;
    public function readyBlock(string $blockId, array $locales): BlockInstance;
    public function projectStage(Audience $audience, string $locale, string $stageId): array;
}
```

`blockReadiness` возвращает тот же `{defaultLocale,readyLocales,locales}` DTO; считает только required textual leaves выбранного блока. Blank material/stage title и другой partial block не блокируют readyBlock. Subset для обоих strict resolvers непустой, уникальный, принадлежит declared locales и содержит исходный defaultLocale; результат не меняет порядок текста/блоков или defaultLocale. Неготовые selected locales нельзя молча отфильтровать. Для полной readiness проверки выбранного locale исходный default может быть ещё неготовым: это не делает готовый перевод partial, но не разрешает выпуск без ready default.

`EditorDraftException extends InvalidArgumentException` имеет `public readonly string $reason` и `public readonly array $issues`; message общий безопасный, не исходный validator message. Причины: invalid_editor_document для structural validation/invalid locale selection, translation_not_ready для incomplete strict resolver, stage_not_found/block_not_found для отсутствующего stable ID. Issues сохраняют описанный выше whitelist. Application maps эти причины в API envelope/status и добавляет readiness из доступного EditorDraft; Domain не содержит status codes/credentials/owner logic. Не парсить человекочитаемые exception messages ради угадывания field path; scoped validation привязывает ошибку хотя бы к фактическому root/stage/block JSON Pointer и известным IDs.

Application проверяет actual media pairs из toArray → stages[*].blocks[*].media через существующий MediaCatalogue::resolve с owner. Не нужен публичный getter internal validated LessonDocument/BlockInstance с заменёнными строками. При назначении новой version identity application меняет только document.id в actual array и снова вызывает fromArray; raw save fingerprint вычисляется до такого rewrite.

План Domain файлов: новые EditorDraft.php, EditorTextFields.php, EditorDraftException.php и небольшой внутренний EditorText.php для wildcard paths/blank predicates/length-preserving replacement/exact restoration. Textual descriptors добавляются в существующие Types/* через optional interface; InteractiveDefinition может разделять helper, конкретный definition объявляет собственные paths без switch по catalogue ID. Stage.php расширяет только layout whitelist. Existing BlockType/InteractiveBlockType signatures, registry registrations, answer validation/grade/publicResult остаются прежними. Если orchestration EditorDraft разрастается, readiness/subset helpers выделяются внутри Domain вместо смешивания application concerns.

План Unit tests: EditorDraftTest.php и EditorTextFieldsTest.php плюс scoped update LessonDocumentTest.php для новых допустимых layouts. Проверки покрывают каждый registered type/version, RU ready/DE partial/zero ready, block extraction независимо от material title, wildcard option/item/role labels и matching sides, case-sensitive cross-locale IDs, unknown fields/config/media/solution, optional notes, invalid UTF-8 и maximum length whitespace-only strings, различие legacy trim и Unicode blank, exact restoration/marker collision, caller reference immutability, subset default/locales/order, три audience privacy и сохранение strict rejection исходного blank документа. Метаданные bounds не дублируют: на boundary реально запускается существующий strict validator.

1. Domain agent: optional textual metadata зарегистрированных definitions + isolated EditorDraft/readiness/strict subset/block resolver + Stage layout enum; Unit tests old strict validation/unknown fields/IDs/Unicode/solutions/teacherNotes/marker collision. Старые validators и publicResult не ослаблять.
2. Backend agent: additive editor_draft/save receipts, private DTO/issues, atomic Studio save/replay, централизованный current-ready resolver и integrations release/start/rehearsal/library/usages/version readers/preview. Runtime читает strict snapshots; owner mutex order 6A сохраняется. Назначить одному владельцу общие Studio/Runtime файлы до параллельных правок.
3. UI agent после fixed DTO: frozen single-flight autosave state machine, honest error/retry/conflict controls, ≤100 local undo/redo, copy/reorder/locale/readiness/layout/три preview. Client unit tests для uncertain ack/retry, later revision conflict, queued edits, undo in-flight и identity change; renderer/browser checks.
4. Backend integration tests: persist/reload RU ready + DE partial и zero ready locales; invalid structural/media save rollback; old PUT не теряет draft; sameUUID/case replay once, different fingerprint conflict, later-revision replay не перезаписывает editor; actual new editor block extraction, released fork immutability; release/rehearsal исключают partial locales и никогда не используют baseline; usages отражают actual partial draft refs; preview privacy/empty values/ownership; MariaDB concurrent saves serialize, один revision winner.
5. Full PHP/frontend/build/Linux MariaDB + browser путь typing → autosave → reload → add DE/partial → undo/copy/layout → ready subset release → immutable rehearsal → другой tab conflict → offline/retry. Проверить уменьшение локального документа/удаление locale без потери receipt и сохранённого чужой вкладкой текста. Mail/production changes в эту design задачу не входят.

Рекомендуемая архитектура сохраняет root proposal и устраняет его основные риски: strict bootstrap решает NOT NULL без fake content, dedicated resolver блокирует stale baseline, authoritative usage readers не забывают partial draft references, registered text metadata не создаёт второй engine. Root утверждает этот контракт перед code implementation; существующий 6A working tree не переписывается.
