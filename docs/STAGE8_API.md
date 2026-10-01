# Этап 8: каталог, проверка публикаций и общая библиотека

Все API используют существующие web cookie-сессии и CSRF. POST/PUT используют
`throttle:studio-write` (60 успешных запросов в минуту на сессию). AccountSession
проверяет актуальный password hash и отзывает устаревшие authenticated sessions.
Новые routes/admin.php должны подключаться до динамического routes/catalog.php.

## Каталог и запуск

- `GET /api/catalog?locale=ru|de&q=&age=&topic=&audience=&format=&duration=short|standard|long&page=1`
  → `{entries, pagination:{page,perPage,total,lastPage}}`, 12 карточек на страницу.
- Карточка: `{slug,versionId,title,description,locales,age:[],topic:[],audience:[],format:[],durationMinutes,coverUrl}`.
- `GET /api/catalog/{slug}?locale=` → `{entry,preview}`. Entry дополняется
  `stages:[{title,durationSeconds}]` и `details:{goals:[],materials:[],devices,conditions}`.
- Preview использует `LessonDocument.project(projector,locale)`: локализованные
  `content`, `stages`, `blocks`, `resources.image`; отсутствуют solutions,
  teacherNotes, origin и runtime. Существующий StageRenderer показывает этот DTO.
- `POST /api/catalog/{slug}/use {locale?}` → `201 {lesson}`: отдельный собственный
  draft, полный авторский документ, новые material/version IDs.
- `POST /api/catalog/{slug}/start {locale?}` → `201 {lesson,session}`: отдельная
  собственная копия и prepared session общего runtime; начало — обычная begin.

Фильтры массивных метаданных используют membership и комбинируются через AND.
Short ≤20 минут, standard 21–60, long >60. Личный release не создаёт каталожную
запись. Каталог показывает только explicitly approved entries с pinned immutable
authoring release и reusable builtin media. Private uploads нельзя опубликовать
ни в документе, ни в cover. Неполный перевод не является release.

## Административный доступ

`users.is_admin` по умолчанию false и отсутствует в User fillable. Регистрация,
профиль, request payload и guest cookies не дают права администратора.
Административные endpoint требуют существующего подтверждённого аккаунта и
актуального DB-флага; `401 authentication_required`, `403 verification_required`
или `403 admin_required`. `GET /api/admin` → `{admin:true}`.

Назначение выполняется только доверенным оператором CLI:

```bash
php artisan lessons:grant-admin exact-existing-verified-email
php artisan lessons:grant-admin exact-existing-verified-email --revoke
```

Это формат CLI, а не утверждение, что конкретный production account уже назначен.
Команда не создаёт аккаунт, не подтверждает почту и не меняет password/email.

## Предложения автора и review

- `GET /api/studio/catalog/submissions` → `{submissions}` только своего verified
  account owner. Guest —401; unverified account —403.
- `POST /api/studio/catalog/submissions`:
  `{lessonId,versionId,expectedLessonRevision,slug,metadata}` → `201 {submission}`.
  Version обязана принадлежать материалу/автору, быть released authoring и иметь
  строгий полный документ. Revision защищает от устаревшего состояния кабинета.
- Metadata: `{translations:{ru:{title,description},de:{title,description}},
  age:[],topic:[],audience:[],format:[],durationMinutes,cover?:{assetId,versionId},
  details?:{ru:{goals,materials,devices,conditions},de:{...}}}`. Набор translations
  точно совпадает с locales released документа; значения справочников active.
- Submission DTO: `{id,lessonId,versionId,slug,revision,status,reason,metadata,
  catalogSlug,submittedAt,reviewedAt}`. Status — pending/returned/approved.
  Source version, owner, slug и submitted metadata неизменяемы. Повтор того же
  proposal возвращает существующий snapshot; другая metadata —409.
- `GET /api/admin/submissions?status=` → `{submissions}`.
- `GET /api/admin/submissions/{id}` → `{submission,document}`. Полный документ
  доступен только администратору для проверки, включая teacher notes/solutions.
- `POST /api/admin/submissions/{id}/review`:
  `{expectedRevision,decision:'approve'|'return',reason?}` → `{submission}`.
  Возврат требует непустую причину ≤2000 знаков; она видна автору. После review
  revision увеличивается. Повтор/устаревшая revision/повторный decision —409.
  Approve проходит CatalogService validation и публикует именно pinned version,
  даже если автор уже редактирует другой draft. Следующая редакция не одобряется.
- Новый исправленный документ требует нового release/proposal. Slug опубликованной
  или отозванной записи нельзя переиспользовать; новая публикация получает новый
  slug. Старые snapshot и запущенные занятия остаются неизменяемыми.
  `taxonomy` и `templates` зарезервированы для служебных API и не являются slug урока.
- `GET /api/admin/catalog` → `{entries:[{slug,versionId,revision,status,metadata}]}`.
- `POST /api/admin/catalog/{slug}/visibility {expectedRevision,visible}` → `{entry}`.
  Отзыв скрывает list/detail/use/start, но не меняет собственные копии и sessions.
  Restore разрешён только ранее одобренному snapshot после повторной валидации.

## Двуязычные справочники

- `GET /api/catalog/taxonomy?locale=ru|de` → `{terms:[{id,kind,key,label,active,revision}]}`,
  только active. Начальные keys согласованы с каталогом и главной.
- `GET /api/admin/taxonomy` → `{terms:[{id,kind,key,labels:{ru,de},active,revision}]}`.
- `POST /api/admin/taxonomy {kind,key,labels:{ru,de},active}` → `201 {term}`.
- `PUT /api/admin/taxonomy/{id}` то же +expectedRevision → `{term}`.
  kind=age/topic/audience/format. Keys стабильны; смена key запрещена. Новая запись
  и деактивация старой сохраняют смысл уже опубликованных metadata. Labels должны
  быть непустыми RU/DE ≤200 знаков. Изменения реально сохраняются в DB.

## Общая библиотека

CommonTemplateService расширяет доступ вокруг существующего TemplateLibraryService,
BlockTemplateRecord и immutable BlockTemplateVersion. Это те же strict blocks,
валидация, createFromLesson, update, instantiate и происхождение экземпляров.
У общих исходников отдельный system owner; личные ресурсы не становятся общими.

- `GET /api/catalog/templates?locale=` → `{templates}` только visible.
- `GET /api/catalog/templates/{id}?locale=` → `{template,preview}`; projector-safe
  BlockInstance projection с resources.image, без private notes/solutions.
- Template summary: `{id,templateId,revision,visible,versionId,locales,type,tags,
  attribution,versions:[{id,versionNo,locales,attribution}],title,description}`.
- `POST /api/catalog/templates/{id}/instantiate {versionId,locales}` → `{block}`.
  Общий механизм создаёт новый instance ID с origin `{templateId,versionId}`;
  копия содержит teacher notes/solution для собственного authoring, ограничивает
  locale maps выбранными locales. Исходный блок/версии не изменяются. После
  скрытия шаблона новая вставка запрещена; сохранённые экземпляры остаются.
- `GET /api/admin/templates` → `{templates}` с labels, block, defaultLocale.
- `POST /api/admin/templates {locales:['ru','de'],defaultLocale,block,
  labels:{ru:{title,description},de:{title,description}},attribution}` →201{template}.
- `PUT /api/admin/templates/{id}` то же +expectedRevision →{template}; новая
  immutable версия, сохранённые прежние экземпляры не обновляются.
- Attribution использует существующий LibraryMetadata contract:
  `{title,tags,author,source,rightsBasis,usageRights}`.
- `POST /api/admin/templates/{id}/visibility {expectedRevision,visible}` →{template}.

Общие шаблоны принимают только reusable builtin media и полные RU/DE переводы.
Никакие private media paths/credentials не копируются в public response.

## Реальная тема и установка

`php artisan lessons:install-neighbor` атомарно устанавливает approved
`kto-moi-blizhnii`, immutable RU/DE release, 13 этапов/2700 seconds и receipt
sourceRevision+SHA256 данных/8 media. Повтор сохраняет snapshot и visibility;
изменившийся источник, metadata или stable ID collision блокирует перезапись.
Иллюстрации owner-supplied/permission; public-domain лицензия не заявляется.

Административные страницы `/{locale}/admin` защищены свежей verified admin
ролью; навигация использует read-only account DTO. Конструктор отправляет
выбранный собственный released snapshot на проверку; личный черновик не
подменяет его. Личная/общая библиотека использует существующую независимую
вставку с undo/autosave. Справочники поступают из БД в главную и каталог.
Production назначение существующего verified аккаунта выполняется отдельной
операторской командой; наличие API/CLI само по себе не означает deployment.
