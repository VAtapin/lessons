# Этап 6: аккаунт, кабинет и жизненный цикл данных

**Контракт реализации 6A.** Аккаунт/claim, история, репетиции, read-time cutoffs и bounded cleanup реализованы; актуальные результаты проверок и production записаны в PROJECT_STATUS.md. Фактическое получение email и автоматическое расписание очистки ещё не подтверждены. Autosave/undo и незавершённые переводы выделены в отдельный [контракт 6B](STAGE6_EDITOR_API.md). Сохраняются контракты [блоков](BLOCK_CONTRACT.md), [runtime](STAGE3_API.md), [библиотеки/медиа](STAGE4_API.md) и [интерактивности](STAGE5_API.md).

## 6A. Идентичность и границы доступа

- Использовать существующие Laravel User, web session guard, password broker и owner_key. Добавить User.owner_key: nullable unique UUID; сервер присваивает постоянный account UUID при создании аккаунта или первом обращении старого User. Null допускает совместимую migration, не является рабочим account owner. User.id остаётся integer, его нельзя записывать вместо owner UUID.
- Существующий `GuestIdentity::key(Request):string` остаётся единым входом: authenticated User → его account owner_key; guest → серверный studio_owner_key. В body/query owner key не принимается; account owner_key не выдаётся как credential. Связь не зависит от email/name и не меняется при обычном login/logout.
- Не создавать второй owner namespace, account_* копии ресурсов или алиас, разрешающий старый guest key в account key. Авторизация материалов, шаблонов, медиа и занятий сохраняет существующие owner predicates.
- Unverified аккаунт имеет доступ к своему приватному workspace и account quota **1 GiB**; guest quota **100 MiB**. File limit остаётся 20 MiB. Лимит определяется сервером по User.owner_key, не boolean/email verified из body. Explicit guest claim требует verified аккаунта. Возможности внешней публикации/приглашения помощников здесь не добавляются.
- Cookie map lesson_participants остаётся независимым credential ученика. Login не делает пользователя учителем чужого занятия или участником по имени. Guest browser credential нельзя восстановить только по lessonId, коду, email или projector URL.
- Все writes требуют CSRF/session lock; User.password/remember token/reset tokens, session IDs и owner keys не попадают в DTO/logs. Authenticated requests проверяют действительность login session; password reset должен лишить старые sessions доступа, включая session, ещё не открывавшую workspace после login.
- Страницы UI только для существующих locales: `/{locale}/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/account`, `/history`; остаются `/studio`, `/library`, `/media`, `/teach/{id}`, `/control/{id}`. Изменение auth вызывает обновление состояния всех вкладок; запрещено сохранить запрос старого workspace под новым владельцем молча.

## Auth HTTP и почта

User DTO: `{id,name,email,verified,uiLocale}`; uiLocale — поддерживаемый язык UI, default текущий UI. Account DTO: `{user:User|null,guestClaimAvailable:boolean,quota:{usedBytes,limitBytes,maxFileBytes}}`. Guest response не содержит чужих account данных.

| Endpoint | Строгое тело / результат |
| --- | --- |
| GET `/api/account` | Account DTO |
| POST `/api/auth/register` | `{name,email,password,passwordConfirmation,uiLocale}` → 201 `{user,verificationRequired:true}`; новая authenticated session, transfer не выполняется |
| POST `/api/auth/login` | `{email,password}` → `{user}`; invalid credentials — generic invalid_credentials 401 |
| POST `/api/auth/logout` | Пустое тело → 204; logout/invalidate/новый CSRF, без account owner в guest context |
| POST `/api/auth/verification-notification` | Authenticated, пустое тело → 202 `{accepted:true}`; повторный запрос не повышает права |
| GET `/email/verify/{id}/{hash}?expires=...&signature=...` | Authenticated + signed; id/email hash соответствуют текущему User; valid → redirect на localized verify/account page |
| POST `/api/auth/forgot-password` | `{email}` → 202 `{accepted:true}` одинаково для отсутствующего/существующего email |
| POST `/api/auth/reset-password` | `{email,token,password,passwordConfirmation}` → `{reset:true}`; token недействителен/истёк → generic invalid_reset 422; новый login нужен отдельно |
| PATCH `/api/account` | Authenticated `{name,uiLocale}` → `{user}`; не меняет email, owner или quota |

Name 1–80 Unicode-символов; email валиден и до 254, нормализуется единообразно в lower case для identity/unique lookup. Пароль не trim/normalize: минимум 12 символов; при существующем bcrypt максимум 72 UTF-8 bytes, без NUL, без тихого обрезания. Хэширование/проверку выполнять framework hasher; confirmation не хранить. Поля неизвестного назначения отвергать.

Login/register регенерируют session ID с уничтожением старого ID и CSRF; контролируемо сохраняют независимую participant map и доказанный pre-auth guest context для claim. Logout не оставляет authenticated account key в studio_owner_key. Если есть ещё не перенесённый guest workspace, сохранить его серверное pending proof в новой session: это не account access. Первый гостевой workspace запрос восстанавливает именно этот unclaimed guest key, чтобы не создавать второй workspace и не потерять его при следующем login. UI также может явно продолжить guest mode через POST `/api/account/guest-continue` (пустое тело → 204), только пока источник не claimed; account logout и отдельная regeneration обязательны. Claimed/account key никогда не восстанавливается как guest; невозможный normal-flow конфликт двух разных действующих guest proofs возвращает guest_context_conflict409 без потери данных. Автоматически переключаться обратно на account или сопоставлять account UUID старому guest credential запрещено.

User реализует framework MustVerifyEmail; verification/reset используют штатные signed URLs/broker с expiry **60 минут**. Reset token одноразовый, хранится hash; reset меняет password/remember token и инвалидирует все прежние authenticated sessions. Auth session validity должна проверяться и на прежних guest-compatible workspace/media routes, если запрос authenticated; одного middleware только на /api/account недостаточно.

Configurable auth throttles: login 5/min по normalized email+IP; register 10/hour/IP; forgot 6/hour по email и IP; verification resend 1/min и 6/hour/User; verification/reset 6/min по User/IP либо IP. 429 не раскрывает existence аккаунта. Forgot не различает email по body, status или mail diagnostics; notification отправляется после DB commit. Не публиковать raw transport errors/tokens. Регистрационный unique-email отказ — registration_unavailable 422, без чужого User DTO.

Отправитель согласован: **lessons@atapin.de**, отправка из PHP приложения через Laravel sendmail/local Plesk transport. Перед выбором MAIL_SENDMAIL_PATH проверить фактический binary, capabilities и PHP CLI/FPM окружение на Plesk; не угадывать sendmail flags/path и не внедрять самодельную MIME-сборку. MAIL_MAILER=log/array допустим для локальных тестов, не подтверждает доставку. Log failover не считается успешной реальной отправкой. Реальную доставку/получение verification/reset проверять отдельным разрешённым действием с выбранным получателем; не отправлять тестовые письма другим людям автоматически. Письма/уведомления не отправлять внутри claim transaction.

## Explicit перенос guest workspace

GET `/api/account/guest-claim` требует authenticated User и возвращает:

```json
{
  "claim": {"available": true, "status": "pending", "counts": {"lessons": 1, "templates": 2, "mediaAssets": 1, "sessions": 1}, "bytes": 2000000},
  "quota": {"usedBytes": 0, "limitBytes": 1073741824, "afterClaimBytes": 2000000},
  "verificationRequired": true
}
```

Counts/bytes относятся только к серверному pre-auth guest key. Status — none/pending/claimed; при claimed возвращаются собственный прежний receipt/result, не данные чужого аккаунта. Preview не создаёт transfer и не даёт API чтения/редактирования guest ресурсов через произвольный source ID.

POST `/api/account/guest-claim`: authenticated + verified, пустое тело → `{claim:{status:"claimed",counts,bytes}}`. Ни source/target owner key, ни список resource IDs клиент не передаёт. Источник — сохранённый сервером guest proof текущего браузера; target — текущий User.owner_key. Receipt уникален по source owner; повтор тем же аккаунтом возвращает прежний результат без повторного движения quota/resources. Чужой claim/неподходящий proof — claim_unavailable 409 без имени/account ID; unverified — verification_required 403; account quota overflow — quota_exceeded 422, полный rollback.

Транзакция переносит **все** свои LessonMaterial, BlockTemplateRecord, MediaAsset и TeachingSession, включая архивные/старые versions и live/rehearsal sessions через их родительскую связь. Bulk ownership update не меняет content revision, document/version IDs, authored origin, released snapshots, answers, state, UUID receipts, join/projector tokens или media files. MediaVersion пути независимы от owner, физического копирования нет. LessonVersion/BlockTemplateVersion/MediaVersion наследуют доступ от перенесённых родителей.

MediaOwnerQuota: заблокировать source/target, суммировать committed versions всех media assets, включая архивные; сверить counters, проверить target 1 GiB, target получает сумму, source counter становится 0. Несоответствие accounting — controlled claim_failed 503 с rollback, не автоматическое исправление/выборочное пропускание файлов. Metadata/attribution не переписываются. При отказе исходные ресурсы доступны по оставшемуся server guest proof для явного продолжения/повтора; никакой частичный transfer не считается успехом.

Permanent guest claim tombstone/receipt записывается **в той же транзакции**, что resources/quota; после commit старый guest key больше не является действующей owner credential, даже если старый server session payload ещё существует. GuestIdentity выдаёт fresh guest key для такого unauthenticated context; начавшийся до transfer write со старым context возвращает identity_changed 409 до mutation. Account-authorized запрос продолжает получать User.owner_key. Pending proof не даёт чтения account ресурсов после logout; tombstone никогда не превращается в alias. Claim security metadata не относится к обычным technical events с очисткой через 30 дней.

## Общий mutex и порядок блокировок

Один application helper `OwnerMutation::transaction(array $ownerKeys, Closure $mutation)` используется всеми изменяющими resource операциями, а не только claim. Это guard существующего owner_key, не новая ownership model. Owner mutex — existing MediaOwnerQuota row, при необходимости нулевая строка; lock держится той же DB transaction до commit/rollback.

1. Присвоить отсутствующий User.owner_key под User row lock отдельной короткой транзакцией **до** owner guard; затем account key неизменяем. Не брать User lock после resource/session locks.
2. Canonical lowercase UUID owner keys, unique и SORT_STRING. Для каждого в этом порядке insert-if-missing quota row, затем lockForUpdate. Claim получает source+target; обычный write — одного владельца.
3. Повторно проверить context/tombstone после mutex. Только затем material/template/asset/session row locks; optimistic revision и permission checks внутри transaction. Media replace больше не берёт asset lock до owner/quota lock. Вложенные service transactions используют ту же connection и не снимают mutex раньше внешнего commit.
4. Для ученического write/activity сначала прочитать session owner без lock, взять соответствующий owner mutex, затем session lock и повторно сверить owner. Если claim изменил owner между чтениями, начать заново с актуальным owner до мутации; owner key здесь не заменяет cookie membership check. Cleanup использует тот же owner → session порядок.
5. Не держать mutex во время mail delivery. File write/reservation остаются атомарными по существующему media контракту; rollback удаляет только файл текущей неуспешной попытки. CLI/jobs изменяющие эти ресурсы тоже используют guard. HTTP session lock не заменяет owner mutex между разными sessions.

Guard должен охватывать Studio create/save/release/snapshot, template mutations/instantiate, media upload/replace/metadata/archive, start/runtime commands/answers/activity, favorites, rehearsal, claim и cleanup. GET без записи не обязан создавать quota row. Контролируемые исключения приводят к DB rollback; нельзя принять error response из HTTP pipeline за успешный commit мутации.

## Schema 6A и DTO кабинета

Additive columns/defaults: User.owner_key nullable unique, User.ui_locale; LessonMaterial.favorite boolean default false; LessonVersion.purpose `authoring|rehearsal` default authoring; TeachingSession.mode `lesson|rehearsal` default lesson, started_at/finished_at nullable UTC, visited_stage_ids nullable JSON, final_aggregates nullable JSON, teacher_notes nullable text (DTO default empty string), details_purged_at/public_access_closed_at nullable UTC. Guest claim tombstones содержат source owner unique, target User/account link и immutable counts/bytes/result/created_at; keys скрыты DTO. Их нельзя массово assign из HTTP.

Текущие User rows получают owner UUID lazy под lock, без destructive migration. Existing sessions mode=lesson; неизвестные started/finished/visited данные не выдумывать. Status=finished сам по себе не доказывает дату завершения: старые finished sessions с finished_at=null автоматически не purged до отдельно согласованной политики backfill. Новое finish записывает finished_at/final aggregates атомарно с прежней UUID командой; повтор finish receipt не меняет anchor. Started_at фиксируется при первом running/begin, не при resume. Visited stages фиксируются сервером при begin/навигации running занятия, не обозначают автоматически выполнение задания.

| Endpoint | Ответ/эффект |
| --- | --- |
| GET `/api/studio/lessons/{id}/versions` | `{versions:[{id,status,purpose,createdAt,current}]}`; свои authoring versions |
| GET `/api/studio/lessons/{id}/versions/{versionId}` | `{version:{id,status,createdAt,document}}`; exact owned parent/version, rehearsal internal не открывается как авторская редакция |
| POST `/api/studio/lessons/{id}/favorite` | `{favorite:boolean}` strict → `{lessonId,favorite}`; boolean set идемпотентен, content revision не меняется |
| GET `/api/studio/sessions?status=&mode=&cursor=` | `{sessions:[HistorySummary],nextCursor:string|null}`; свои lesson/rehearsal, limit 30, стабильный server cursor |
| GET `/api/studio/sessions/{id}/history` | `{history:HistoryDetail}`; доступ/expiry проверяются до подробностей |
| POST `/api/studio/sessions/{id}/again` | Пустое тело → 201 `{session:TeacherState}`; новый lesson session на той же source version/locale, новый ID/код/token, clean state |

Favorite относится только к owned LessonMaterial до публичного каталога этапа 8, не открывает права на foreign lesson. Lesson summaries дополнительно содержат favorite; version/release не делает материал публичным.

HistorySummary: `{id,lessonId,lessonVersionId,title,locale,mode,status,revision,createdAt,startedAt,finishedAt,visitedStageIds,detailsAvailable,detailsExpiresAt,historyExpiresAt}`. Даты ISO UTC/null; title из фиксированного snapshot/locale. VisitedStageIds=null означает неизвестные legacy данные, [] — сервер знает, что ни один этап ещё не посещён. HistoryDetail дополнительно `{aggregates,teacherNotes,participants,answers}`: подробные participant/answer DTO этапа 5 только пока разрешены retention; после cutoff arrays пустые и detailsAvailable=false. TeacherNotes — private session note string, не переписывает authored notes документа. PATCH `/api/studio/sessions/{id}/history` `{expectedRevision,teacherNotes}` (строка до 5000) → `{history:HistoryDetail}`; меняет note под session lock/revision, увеличивает revision на 1, при несовпадении возвращает revision_conflict 409 с актуальным owned state. Finished note допускает правку истории, но не новые runtime ответы/управление.

История дополнительно сообщает `lessonArchived` и `lessonPurged`. `snapshotDocument` доступен владельцу только при `detailsAvailable=true`, иначе null. Он позволяет группировать имена и ответы под исходным вопросом без доступа к редактору удалённой копии. Кабинет может завершить незавершённое занятие после подтверждения штатной UUID/revision-командой `finish`; история сохраняется.

Корзина: `POST /api/studio/lessons/{id}/archive {expectedRevision,archived}` переключает архив и записывает UTC `archivedAt` (при восстановлении null). Для старых архивов migration использует существующий `updated_at` как единственный известный timestamp. `POST /api/studio/lessons/{id}/purge {expectedRevision}` и `POST /api/studio/lessons/trash/purge {lessons:[{id,expectedRevision}]}` возвращают `{deletedIds}`. Bulk ограничен 100 показанными копиями и атомарен. Только владелец, только архивные копии, под owner/material locks; pinned catalog source защищён. `purged_at` — терминальный tombstone: копия исчезает из кабинета и корзины, восстановление/редактирование/новый запуск невозможны. Immutable версии и существующие занятия/история сохраняются; физического удаления снимков или ученических ответов эти действия не выполняют.

Aggregates — массив per-block DTO `{stageId,blockId,type,schemaVersion,submittedCount,gradedCount,correctCount,incorrectCount}`; для choice/poll допустим `options:[{optionId,count}]`, roles — `roles:[{roleId,count}]`, signals — `signals:{readyCount,questionCount}`. Все counts — integers ≥0. Не сохранять participant IDs, имена, свободные тексты, displayText или per-person role/signal assignments. Для answer без grade увеличивается submittedCount, а не graded/correct/incorrect; outcome для типов без grade не имитировать. Before detail cleanup зафиксировать aggregate snapshot; authored content/version остаётся исходным. Продолжение открывает прежние teach/control routes для unfinished session, не создаёт новый запуск. Again не переносит answers/участников/block states/timer/moderation. Unfinished real session никто автоматически не переводит в finished ради очистки.

## Репетиция тем же runtime

POST `/api/studio/lessons/{id}/rehearsals` `{expectedRevision,locale?}` → 201 `{session:TeacherState}`. Под owner/material lock проверить текущий saved strict document/media и revision; создать новый **immutable internal LessonVersion purpose=rehearsal**, не назначая его current_version_id и не выпуская/изменяя текущий draft. Immutable internal snapshot проверяется так же, как released content; его нельзя редактировать обычным Studio save. TeachingSession mode=rehearsal работает с этой версией и обычной state machine/таймером/блоками/UUID receipts.

- Rehearsal — owner-only, без реальных учеников. Ordinary join отклоняет rehearsal code; обычные participation/projector routes не разрешают его по cookie ученика или одному bearer token. Random join/projector значения могут оставаться в schema для совместимости, но не выдаются как invitation DTO.
- GET `/api/studio/rehearsals/{id}/preview/{audience}` (audience student/projector) → `{session:PublicState}` только владельцу. Применять обычную audience projection, не возвращать teacher notes/solution в student preview до разрешённого reveal.
- POST `/api/studio/rehearsals/{id}/answers` `{stageId,blockId,value}` → `{session:PublicState}`; owner-only test answer. Сервер создаёт одно private preview participation при старте, чтобы переиспользовать существующий RuntimeAnswers, а не доверяет body participantId. Оно не реальный ученик, не доступно по ordinary join и не смешивается с live history.
- Все media выдачи rehearsal требуют владельца exact pair/current stage, включая projection file endpoint; один projector token не авторизует файл/экран. New GET `/media/rehearsal/{sessionId}/{assetId}/{versionId}` — owner-only active reference, private/no-store.
- Existing teacher/control command endpoint доступен владельцу и применяет ту же state machine. Второго renderer/движка/каталога типов нет. Rehearsal versions исключены из authoring version selector; changing draft/шаблон/медиа version не меняет начатую репетицию.

## Retention: согласованные anchors и ограничения

Все сроки считает сервер по UTC; дата смены owner/claim не начинает новый срок и не обновляет original anchor. Public/private DTO проверяют cutoff при чтении, даже если cleanup job задержался. При закрытом public access join/participation/projector и все соответствующие media routes возвращают 404; finished owner history остаётся в пределах account history срока.

| Данные | Срок/anchor | Очистка |
| --- | --- | --- |
| Finished guest lesson sessions | 30 дней от finished_at | Удалить session/участие/ответы/state; authored material/media/template не трогать |
| Имена/подробные ответы finished lesson sessions | 30 дней от finished_at | Удалить participant-linked details, value/displayText/publication/личные роли/сигналы, сохранить anonymous aggregate/history |
| Account finished history без этих подробностей | 2 календарных года от finished_at | Удалить запись истории/session после срока, не authored versions/resources |
| Rehearsal sessions/internal snapshot | 7 дней от session.created_at | В 6A автоматически очищать **только finished**; unfinished сохраняются консервативно |
| Обычные technical events/command receipts | 30 дней от created_at | Удалять технические записи, не guest claim security tombstones |

Prepared/running/paused **реальные** занятия никогда не purge автоматически. Abandoned expiry/правило last-owner-activity отложены до отдельного решения владельца. Для репетиций выбрано консервативное finish-only, без скрытого idle threshold; дальнейшее сокращение по recent owner poll требует отдельной настройки/контракта. Legacy finished с неизвестным finished_at не истекает на основе guessed updated_at. Guest materials/media/templates не имеют утверждённого срока удаления; эти jobs их не удаляют. Квота по-прежнему считает все media versions, включая архивные, и не уменьшается от удаления session.

После 30 дней command receipt replay window завершается: исходное успешно исполненное тело содержит старую expectedRevision и не может повторно примениться после удаления receipt (revision монотонна, reset отсутствует). Такой retry возвращает revision_conflict вместо acknowledged receipt; клиент должен reconcile, не переиспользовать UUID с другим телом. Command idempotency за пределами технического окна не обещается. Claim tombstones сохраняют отдельную неизменяемую security семантику.

Предложенный job/CLI `lessons:retention --dry-run` показывает только counts, без names/answers/SQL secrets. Применение — scheduled bounded batches, owner → session locks, recheck status/anchor/ownership, идемпотентность и generic operational failures. Schedule/Plesk PHP 8.5 и очередь документируются перед включением; write cleanup не запускается в этой задаче. Нельзя очищать активное занятие по одному старому created_at/последнему student poll.

Перед удалением internal rehearsal version проверить, что она не current authoring version и больше не referenced session; удалить только временную purpose=rehearsal версию. Не изменять authored JSON/immutable released versions ради очистки names: participant details относятся к runtime. Backups с данными до cleanup приватны; их retention/rotation и повторное применение cutoffs до возвращения restored сайта online входят в проверку эксплуатации. Успешный DB/media bundle не означает, что restore/retention уже проверены.

## 6B. Autosave, undo и языковые границы — отдельная реализация

Этот раздел фиксирует исходную границу 6A. Её расширение с private partial translations, readiness и save receipts описано в [STAGE6_EDITOR_API.md](STAGE6_EDITOR_API.md); оно не ослабляет строгие опубликованные/runtime snapshots. Подтверждённый статус расширения указан в PROJECT_STATUS.

- 6A сохраняет существующий ручной full strict save. Autosave нельзя объявить готовым вместе с auth/history. 6B использует тот же Studio save + strict revision/domain/media validation; autosave не создаёт параллельное хранилище опубликованных документов.
- Для network retry autosave нужен отдельный согласованный saveId UUID/receipt/fingerprint и acknowledged revision; это расширение, не уже существующий HTTP параметр. До его реализации pending save сравнивает server revision/document и требует явного reconcile, не перезаписывает другой tab автоматически.
- Локальное undo/redo относится к editor draft, не отменяет чужой server commit, release, runtime command или media file replacement. Unsaved/invalid/saving/saved/conflict/offline различаются; local state не показывается как persisted.
- Добавление locale выполняется явно в UI; все стабильные IDs сохраняются. В этом draft **нет server persistence неполных переводов**: документ перед save/release/rehearsal обязан пройти текущую полную validation. Если нужны незавершённые translated drafts, root сначала утверждает отдельную compatible schema/readiness contract, ограничения preview/release и защищённые DTO; не ослаблять BlockInstance/teacherNotes strict translations молча.
- Preview uses общий renderer и registered definitions; новая locale не меняет fixed locale уже начатого session. Favorites/личные версии не превращаются в публичный каталог.
- Изменение email/password, account deletion и полный workflow удаления собственных данных требуют отдельного точного lifecycle/revocation контракта до реализации; не имитировать рабочие кнопки. На этом draft нельзя объявить весь этап 6 завершённым только по 6A.

## Проверки и параллельный handoff

- Auth: register/login/logout, real session ID/CSRF rotation, duplicate email, password byte boundaries, unverified own workspace/account quota, verified/signed expiry/resend throttles, generic forgot/reset, token single use и invalidation старых sessions на всех workspace/file routes. Local mail fakes проверяют recipient/content/count, не delivery.
- Claim: все четыре ресурса+старые/archive versions+live/rehearsal migrate вместе, IDs/files/snapshots/answers unchanged, stale guest contexts не видят account data/не пишут orphan, partial failure rollback, account quota accounting/overflow, receipt replay/foreign source, две browser sessions и MariaDB concurrent create/upload/claim. Проверить одинаковый lock order, не только SQLite happy path.
- History: owner-only list/detail/version binding, pagination, legacy unknown data, continue vs again, notes отдельно от immutable lesson, anonymous aggregates, detailed cutoffs и закрытие public media независимо от задержки job.
- Rehearsal: saved snapshot при current draft сохраняется неизменным; no ordinary join/bearer access; owner student/projector preview и реальный test answer; draft/media replacement не меняет старый rehearsal; future-stage file404.
- Retention: точные UTC boundaries и calendar years, finished-only, protected prepared/running/paused/legacy-unknown, claim не продлевает anchors, free displayText исчезает, no student identities in aggregates, dry-run без writes, повторный batch, rollback/error/restart, no authored file deletion. Проверить backup/restore процедуру отдельно.
- Root фиксирует этот draft перед стартом. Auth/claim агент владеет User/GuestIdentity/auth routes/shared owner guard и integration в существующие mutations; history/rehearsal агент — session/version metadata, history/rehearsal services, retention и свои tests; UI агент — кабинет и отдельный 6B editor scope. Общие RuntimeService/StudioService/media edits назначаются одному владельцу или выполняются последовательно, не параллельно в одном файле.
- Совместное проведение — этап 7: session-scoped grants, срок/отзыв invitation и атомарный active presenter. Не добавлять помощника посредством owner_key substitution/account claim; claim не переносит/создаёт чужие grants. Production changes/mail sends требуют отдельной разрешённой задачи; этот документ их не выполняет.
