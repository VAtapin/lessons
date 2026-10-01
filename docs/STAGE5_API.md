# Этап 5: базовые интерактивные блоки и модерация

Согласованный контракт для параллельной реализации в существующем движке. Документ описывает целевое поведение этапа 5, а не подтверждает готовность кода, CI или production. Сохраняются [контракт документа](BLOCK_CONTRACT.md), [runtime этапа 3](STAGE3_API.md) и [библиотека/медиа этапа 4](STAGE4_API.md).

## Границы и идентичность

- Документ остаётся `schemaVersion: 1`. Версия конкретного блока независима от версии документа. Старые `core.text@1`, `core.image@1`, `core.single-choice@1`, выпущенные снимки и занятия продолжают читаться без скрытой перезаписи.
- Вставки заготовок получают независимые block IDs. Все состояния/ответы привязаны к занятию и ID экземпляра блока, а не к template origin, подписи, порядку или locale. ID остаются case-sensitive.
- Текущий этап, тип блока, решения, допустимые IDs и настройки берутся из immutable LessonVersion занятия. Клиент не задаёт тип проверки, правильность, владельца, участника или состояние модерации.
- Владение определяется GuestIdentity; участие — существующей cookie map `lesson_participants`. Имя, body `participantId` или код занятия не дают полномочий участника. Чужие ресурсы скрываются 404.
- Все writes сохраняют CSRF, session lock и существующие rate limits. Проверки ролей, вместимости, ответов, навигации и команд сериализуются row lock одного TeachingSession.
- Попытка имеет фиксированный `attemptNo: 1`. Reset, история попыток, автоматическое открытие при возврате на этап, баллы, аккаунты, помощники, почта и retention не входят в этот ограниченный шаг.

## Одна регистрация типов

`BlockRegistry::core()` регистрирует доверенные определения `(type, schemaVersion)`. Второго каталога, отдельного списка серверных схем или поиска PHP-класса из authored JSON нет. Неинтерактивные типы реализуют прежний BlockType. Интерактивные дополнительно реализуют:

```php
interface InteractiveBlockType extends BlockType
{
    public function validateAnswer(BlockInstance $block, array $value): array;
    public function grade(BlockInstance $block, array $value): ?bool;
    public function publicResult(BlockInstance $block): ?array;
    public function initialState(): string;
}
```

- `validateAnswer` проверяет строгую форму, неизвестные поля, типы, пределы и IDs; возвращает нормализованный JSON DTO либо бросает доменную ValidationException. Метод не обращается к Laravel/БД, не проверяет владельца или доступность места роли.
- Тип DTO определяется зарегистрированным типом блока. Клиентский discriminator не принимается; поля value перечислены ниже. `grade` принимает уже валидированный DTO и возвращает `true`, `false` либо `null` для отсутствующей/неприменимой проверки. Свободный ответ не оценивается автоматически.
- `publicResult` возвращает только разрешённую форму решения из таблицы ниже либо `null`. Runtime вызывает/добавляет её лишь после `revealed`. Нельзя вернуть произвольный authored `solution`, teacherNotes или служебный объект определения типа.
- `initialState`: `core.single-choice@1` — `open` ради прежних занятий; новые answerable типы — `prepared`; `core.roles@1` и `core.signals@1` — `open`. `core.single-choice@1` реализует тот же интерфейс с прежними схемой и defaults.
- Aggregate poll results и опубликованные письменные ответы вычисляет runtime из разрешённых записей БД; `publicResult(BlockInstance)` не получает ответы участников и не имитирует агрегирование.

## Общая форма содержания

Неизвестные поля отклоняются. Контент содержит ровно все объявленные локали, без fallback/частичных переводов. Стабильные option/item/role IDs уникальны внутри соответствующего набора и совпадают во всех локалях; перевод может менять подписи и порядок. Проверки нормализации не переводят IDs в lowercase. Медиа остаётся `{assetId,versionId}`, проходит существующую owner-aware validation; resource URLs не записываются в JSON.

У любого блока добавляется optional top-level `teacherNotes: {locale: string}`. Если поле присутствует и непусто, оно содержит ровно объявленные локали, строки до 5000 символов (пустая строка допустима). При отсутствии или пустом объекте DTO использует пустой набор; `toArray()` **не добавляет teacherNotes**, сохраняя старую форму снимков. Teacher projection получает только заметку выбранной locale; student/projector projection всегда исключает поле. Это отдельное закрытое поле, не свойство публичного config/content и не правильное решение.

Все строки отображаются как текст с экранированием. Новые static виды задают семантику/расположение, не исполняемый HTML/Markdown.

### Неинтерактивные дополнения

| Тип | Строгий `content[locale]` | `config` | `solution` |
| --- | --- | --- | --- |
| `core.text@2` | `{title, text, source}`: title/source — строки, могут быть пустыми; text непустой | `{presentation: paragraphs\|list\|quote}`, default `paragraphs` | Только null |
| `core.prompt@1` | `{text}`: непустая строка | `{kind: discussion\|instruction\|reflection, target: class\|pair\|group}`, defaults `discussion`, `class` | Только null |

У обоих типов media пустой объект. Paragraphs сохраняет переводы строк; list показывает непустые строки как элементы; quote показывает text и source. Эти значения не дают произвольных CSS/HTML-настроек. Подсказки для обсуждения/инструкции — teacherNotes. Итог/рефлексия собирается из этих компонентов либо свободного ответа; отдельная система итоговых баллов не создаётся.

### Интерактивные дополнения

`allowRepeat` — строгий boolean, default false там, где указан. Если решение необязательно, `solution=null` даёт `grade=null` и не раскрывает правильность. У всех новых интерактивных типов media пустой объект.

| Тип @1 | Строгий `content[locale]` | `config` | Answer value | Закрытый solution → publicResult |
| --- | --- | --- | --- | --- |
| `core.multiple-choice` | `{question,options:[{optionId,text}]}` | `{allowRepeat,minSelections,maxSelections}`; defaults false, 1, 2; редактор может явно выбрать другой максимум | `{optionIds:[ID]}` | null либо `{optionIds:[ID]}` → тот же whitelist |
| `core.poll` | `{question,options:[{optionId,text}]}` | `{allowRepeat}`, default false | `{optionId:ID}` | Только null → null |
| `core.free-response` | `{question}` | `{allowRepeat,maxLength}`, defaults false, 500 | `{text:string}` | Только null → null |
| `core.sequence` | `{question,items:[{itemId,text}]}` | `{allowRepeat}`, default false | `{itemIds:[ID]}` | null либо `{itemIds:[ID]}` → тот же whitelist |
| `core.matching` | `{question,left:[{itemId,text}],right:[{itemId,text}]}` | `{allowRepeat}`, default false | `{pairs:[{leftId,rightId}]}` | null либо `{pairs:[{leftId,rightId}]}` → тот же whitelist |
| `core.roles` | `{text,roles:[{roleId,text}]}` | `{capacities:{roleId:integer}}` | `{roleId:ID\|null}` | Только null → null |
| `core.signals` | `{text}` | Пустой объект | `{ready:boolean,question:boolean}` | Только null → null |

Пределы и нормализация:

- Question/text и названия элементов — непустые строки. Options — 2–12; sequence items — 2–12; matching — 2–12 элементов на каждой стороне, одинаковое количество; roles — 1–12.
- Multiple-choice: `1 <= minSelections <= maxSelections <= option count`; ответ без повторов, в этом диапазоне. Нормализовать optionIds сортировкой `SORT_STRING`, поскольку выбор — множество. Решение удовлетворяет тем же ограничениям; оценка — точное равенство множеств.
- Sequence: answer/solution содержит каждый itemId ровно один раз; порядок значим, сортировать ответ нельзя. Оценка — точное совпадение порядка.
- Matching: каждая сторона покрыта целиком, без повторов leftId/rightId. IDs проверяются в соответствующей группе, не по подписи. Нормализовать пары по leftId; оценка — точное равенство пар. Механика many-to-one не входит в этот минимум.
- Free-response: `maxLength` — integer 1–1000, default 500; ответ — непустой после проверки whitespace, не длиннее maxLength по Unicode-символам. Сохранить отправленный текст без HTML-интерпретации/тихого обрезания. Grade всегда null.
- Roles: capacities содержит ровно role IDs, integer 1–100 для каждого. Это общий config, не различающиеся ограничения в переводах. null освобождает роль; одна текущая роль на participant/block. Повтор выбора той же роли идемпотентен. Переход на другую проверяет новую вместимость атомарно и не теряет прежнюю роль при отказе. Capacity проверяет runtime, не доменный валидатор; исчерпанная вместимость возвращает `role_full` 409.
- Signals: оба поля обязательны и boolean. `acknowledged` клиент отправлять не может. Это серверное состояние реакции ведущего: новый запрос question false→true сбрасывает его; question=false снимает запрос/ack; изменение ready при неизменном question сохраняет ack. Roles/signals допускают изменение своего состояния, без allowRepeat/проверки правильности.
- Poll grade всегда null. Правильность, option count, место в роли и сигнал не определяются клиентскими значениями beyond валидированного value.

## Persistent ответы и совместимость

Сохранить таблицу session_answers и её уникальность `(teaching_session_id,session_participant_id,block_id)`. Binary collation block_id остаётся обязательной. AttemptNo фиксирован 1, поэтому новый индекс попыток и backfill старых ответов не нужны.

Совместимое изменение schema: nullable JSON `value`; прежний `option_id` становится nullable, существующие значения сохраняются. Добавить revision ответа (default 1), nullable moderation status/display text, published boolean (default false) и acknowledged boolean (default false). JSON value имеет casts array; generic value проходит DTO до записи. Никаких sentinel пустых строк или destructive migrations. Rollback новой migration оставляет `option_id` nullable: возврат NOT NULL не должен принудительно преобразовывать generic ответы.

- Legacy строка с value=null/option_id!=null читается как `{optionId}`. Чтение не переписывает её. Single-choice write сохраняет option_id для совместимости, остальные типы используют value и option_id=null.
- Grade выводится из snapshot + нормализованного value; не доверять присланному grade или mutable draft.
- Ответы не увеличивают session revision команд учителя. Новая запись имеет answer revision 1; изменение значения увеличивает её на 1. Повтор того же нормализованного value не меняет revision, moderation или публикацию.
- Иное значение при allowRepeat=false — answer_locked 409; при allowRepeat=true — атомарная замена. Для изменённого free-response снять approved/rejected и публикацию, установить pending, очистить displayText; нельзя автоматически публиковать новую редакцию.
- Не возвращать DB model напрямую в публичный JSON.

### HTTP ответа

Существующий endpoint: `POST /api/participation/{sessionId}/answers`.

```json
{"stageId":"stage-id","blockId":"choice-id","optionId":"first"}
```

Legacy форма допустима только для `core.single-choice@1`. Новая форма:

```json
{"stageId":"stage-id","blockId":"multiple-id","value":{"optionIds":["first","second"]}}
```

Не принимать optionId и value одновременно. Поля участника/правильности/moderation, дополнительные ключи внутри value и attemptNo в body не разрешены. TrimStrings/ConvertEmptyStringsToNull не должны менять отправленный value.text: проверять исходный JSON и сохранять его текст. Старый endpoint/envelope `{session: studentState}` сохраняется. Принимается только ответ реального cookie-участника running занятия на блок активного stage со статусом open. Wrong stage/unknown IDs/value — invalid_action 422; session/block не принимает ответы — invalid_state 409. Пауза таймера отдельно от занятия не блокирует открытые упражнения. Навигация назад сохраняет ответы и статус.

## Состояния упражнений и команды

Persistent session_block_states: session/block ID, status, attemptNo=1; unique(session,block), binary block ID. Если строки ещё нет, runtime вычисляет initialState зарегистрированного типа и сохраняет только при изменении. GET/reload не создаёт новый цикл упражнения, не переписывает старые занятия. Неинтерактивный блок состояния ответов не имеет.

Переходы: prepared → open → closed → revealed. `block.open` также разрешает closed → open в той же попытке, сохраняя ответы. Revealed — конечное состояние этого блока в шаге без reset; не скрывать уже раскрытый ответ посредством нового открытия. Begin/resume/navigation/timer не меняют block states. Новые prepared упражнения явно открывает ведущий; legacy single-choice остаётся open по умолчанию.

Все новые действия используют прежний endpoint `POST /api/studio/sessions/{sessionId}/commands` и envelope:

```json
{"commandId":"UUID","expectedRevision":1,"action":"block.open","payload":{"blockId":"block-id"}}
```

UUID сохраняется canonical lowercase (ввод uppercase принимается и нормализуется для совместимости этапа 3), строгий integer expectedRevision, fingerprint полного исходного тела, owner check до receipt, row lock, атомарные command receipt/state/answer mutations. Replay возвращает текущий teacherState и acknowledgedCommandId без эффекта. Новый успешный command повышает session revision ровно на 1; failed command не создаёт receipt. Другое тело того же UUID — command_conflict 409; stale revision — revision_conflict 409; ответы конфликтов содержат только авторизованный teacherState, как в этапе 3.

| Action | Строгий payload | Условия/эффект |
| --- | --- | --- |
| `block.open` | `{blockId}` | Интерактивный блок активного этапа, prepared/closed → open |
| `block.close` | `{blockId}` | Активный open → closed; ответы сохраняются |
| `block.reveal` | `{blockId}` | Активный closed → revealed; только whitelisted results |
| `answer.moderate` | `{answerId,expectedAnswerRevision,status,displayText?}` | Только free-response; status approved/rejected; публикация снимается |
| `answer.publish` | `{answerId,expectedAnswerRevision}` | Только approved ответ текущего active free-response; published=true |
| `answer.unpublish` | `{answerId,expectedAnswerRevision}` | published=false, исходник/одобрение сохраняются |
| `role.assign` | `{blockId,participantId,roleId}` | Активный open roles; participant этого занятия; ID либо null; capacity под lock |
| `signal.ack` | `{blockId,participantId}` | Существующий question=true в signals активного этапа этого занятия; acknowledged=true |

Teacher commands допустимы в prepared/running/paused занятии, finished отклоняет новые mutations, как этап 3. Контекстные действия проверяют принадлежность ответа/участника/block текущему занятию; данные другой комнаты не дают доступа. Role assignment и ack — полномочие текущего владельца; помощники не вводятся. Исчерпанная вместимость или закрытый блок не обходятся teacher assignment.

Answer ID — существующий integer DB ID, expectedAnswerRevision — строгий integer >=1. Сопоставление answer revision выполняется под тем же session lock до изменения. Устаревшая answer revision — answer_revision_conflict 409 с текущим teacherState, без receipt/потери локального moderation текста. Успешная moderation/publication/assignment/ack, меняющая ответ, увеличивает его revision на 1; новый UUID команды по-прежнему увеличивает session revision ровно на 1.

## Модерация письменных ответов

- Новый free-response pending, published=false, displayText=null. Оригинал хранится в value.text и не заменяется редактором ведущего.
- `answer.moderate` approved: optional displayText — непустой обычный текст в пределах maxLength блока. Если не указан, взять оригинал. Rejected: displayText не передаётся, публикация снимается. Каждая модерация снимает предыдущую публикацию; повторное одобрение само по себе не публикует текст.
- `answer.publish` разрешён только для approved/current-stage/current-block ответа. Статус упражнения может быть open/closed/revealed: публикация письменного текста — отдельное явное педагогическое действие, не обход общего раскрытия оценочного решения.
- Снятие публикации или редактирование accepted ответа сразу убирает его из projector. Переключение stage исключает письменные ответы другого этапа из публичного state.
- Projector показывает только опубликованный displayText, анонимно. Student не получает чужие письменные ответы даже после публикации. Teacher показывает отдельно original, displayText, moderation status, publication и revisions.

## DTO и безопасные проекции

TeacherState сохраняет существующие поля и добавляет `blockStates: [{blockId,status,attemptNo}]` для интерактивных блоков всего snapshot. Answer DTO:

```json
{
  "id": 1,
  "revision": 1,
  "participantId": "UUID",
  "blockId": "block-id",
  "attemptNo": 1,
  "value": {"text": "Original answer"},
  "grade": null,
  "moderation": {"status": "pending", "displayText": null, "published": false},
  "acknowledged": false
}
```

Для не-free-response moderation=null; acknowledged используется signals, для остальных false. Для single-choice оставить прежний `optionId` вместе с value. Teacher видит свою полную таблицу ответов и grade независимо от reveal; данные не попадают в publicStage.

Публичный active stage сохраняет authored audience projection и добавляет интерактивному блоку `runtime: {status,attemptNo,results?}`. Results отсутствуют до revealed, кроме отдельно опубликованных projector free-response текстов. Для roles дополнительно доступна операционная `availability: [{roleId,used,capacity}]` без имён/participant IDs: выбор места требует актуальной вместимости и не является раскрытием оценочного решения. Формы:

| Тип | `runtime.results` |
| --- | --- |
| Single/multiple-choice, sequence, matching | whitelisted publicResult либо отсутствует при solution=null |
| Poll | `{counts:[{optionId,count}],totalAnswers}`; без имён/participant IDs, только после revealed |
| Free-response на projector | `{published:[{text:displayText}]}`; только явно published, без оригинала/answerId/participantId/name |
| Free-response на student, roles, signals | Отсутствует |

`ownAnswers` сохраняет прежние blockId/optionId single-choice, добавляет id/revision/attemptNo/value/status. Status — pending/approved/rejected для free-response, submitted для остальных. Допустим own grade только после revealed, до него null; signals дополнительно собственный acknowledged. Это только записи cookie-участника, включая сохранённые ответы прежних этапов. Собственный оригинал не даёт доступа к чужим оригиналам или редакциям. Projector не получает ownAnswers.

Публичные content/config/media не содержат notes, correct flags, solution, teacher credentials, origin, command receipts, unpublished text или чужие ответы. TeacherNotes исключается доменной audience projection до application runtime/media projection. Ошибку missing reference/unsupported schema выявлять до запуска; не отрисовывать неизвестный тип как готовое упражнение.

Role capacities передаются JSON object, включая числовые строковые ID «0»/«1»: HTTP boundary нормализует только config.capacities зарегистрированного core.roles@1, не меняя доменный/storage формат. Teacher/student/projector response читает persisted block states одним запросом и использует единый снимок при построении проекций и own answers; число прошлых ответов не увеличивает число таких запросов.

## Проверки и порядок интеграции

- Domain tests: strict forms/defaults всех версий, границы количества/длины/capacity, stable IDs across locales, неизвестные поля, teacherNotes privacy, normalized DTO, nullable grade и whitelist publicResult. Старые round-trip/snapshots не получают новые пустые поля.
- Runtime tests: legacy optionId/body/DB adapter, prepared/open/closed/revealed и pause/finish, back/reload без сброса, один и несколько блоков одного этапа, две независимые комнаты/две вставки одной заготовки, idem answers, allowRepeat, stale revisions, UUID replay/fingerprint и rollback.
- Moderation tests: original не меняется, approve не публикует, publish/unpublish и concurrent answer edit снимают показ, foreign IDs404, expectedAnswerRevision conflict, active-stage-only projector text, анонимность, student отсутствие чужих ответов/решений до reveal.
- Roles/signals tests: последняя роль не выдаётся двум участникам, отказ не теряет старую роль, release/назначение/повтор идемпотентны, чужой participant не допустим, ack адресный/серверный, capacity и case-sensitive IDs проверяются на реальной MariaDB.
- UI: каждый заявленный тип создаётся/сохраняется/восстанавливается/вставляется из библиотеки и работает в общем renderer/panel; RU/DE, клавиатура и 360 px. Sequence/matching имеют доступные controls без обязательного drag. Не добавлять отдельный renderer или специфичную программу урока.
- После фиксации этого контракта параллельно: домен/валидаторы и unit tests; runtime schema/state/answer adapter; UI editor/renderers. Подключение runtime handlers зависит от готового доменного интерфейса, UI API — от готовых endpoints. Root интегрирует projection/schema checks и запускает полные проверки.
- Migrations совместимы с существующими данными; старые релизы/запуски не переписываются. Production обновляется только отдельной разрешённой задачей с DB и private media backup; этот документ не выполняет deployment.
