# HTTP API этапа 2

Контракт реализован серверными services, Vue-конструктором и renderer. Проверки и ограничения записываются в PROJECT_STATUS.md. Это минимальная вертикаль, а не вся первая версия платформы.

Все API работают через Laravel web middleware: cookie session, CSRF для mutations, JSON response. GuestIdentity::key(Request): string создаёт UUID владельца в серверной сессии (`studio_owner_key`). Владелец не берётся из request body. Учительские URL не являются credentials. Ответ private API содержит только принадлежащие текущему владельцу ресурсы; чужие ID — 404. Code комнаты даёт только право присоединения ученика.

## Материалы

Models: LessonMaterial (id UUID, owner_key UUID, revision integer, current_version_id FK), LessonVersion (id UUID, lesson_material_id FK, status draft/released, document JSON). Текущая версия задаётся явным current_version_id. document.id всегда равен version.id. Выпущенное содержание не обновляется; следующая правка создаёт новый draft с новым ID. revision материала монотонен и проверяется при записи под row lock.

StudioService public API для runtime: findOwned(string ownerKey, string lessonId): LessonMaterial; release(string ownerKey, string lessonId, int expectedRevision): LessonVersion. Release повтор уже выпущенной версии с текущей revision возвращает тот же снимок. Регистрация не реализуется; владение привязано к текущей cookie-сессии браузера.

- GET /api/studio/lessons → {lessons:[{id,title,revision,status,updatedAt}]}
- POST /api/studio/lessons {document} → 201 {lesson:{id,revision,status,versionId,document}}
- GET /api/studio/lessons/{id} → {lesson:{id,revision,status,versionId,document}}
- PUT /api/studio/lessons/{id} {expectedRevision,document} → тот же lesson envelope
- POST /api/studio/lessons/{id}/release {expectedRevision} → {lesson:{...}}
- GET /api/studio/media → {media:[{assetId,versionId,url,labelKey}]}; только реально существующие встроенные версии, не имитация uploader.
- GET /media/builtin/{versionId} → конкретный файл из доверенного manifest; произвольных путей/URL нет.

Shared MediaCatalogue::all(): array; assertDocument(LessonDocument): void; resolve(assetId,versionId): array (url). Начальная версия `builtin-conversation-v1`, assetId `builtin-conversation`, файл UI-Design/1.png, labelKey `media_conversation`. Все ссылки core.image проверяются при сохранении. Загрузка/права пользовательских медиа — этап 4.

## Проведение

POST /api/studio/lessons/{id}/sessions {expectedRevision,locale} фиксирует версию через StudioService и создаёт отдельное занятие → 201 {session:teacherState}. Учитель-владелец читается из GuestIdentity.

- GET /api/studio/sessions/{id} → {session:teacherState}
- POST /api/studio/sessions/{id}/stage {expectedRevision,stageId} → {session:teacherState}; атомарная навигация
- POST /api/join {code,name} → {participant:{id,name},sessionId}; participant ID сохраняется в серверной cookie-session, не используется для impersonation из body
- GET /api/participation/{sessionId} → {session:studentState}
- POST /api/participation/{sessionId}/answers {stageId,blockId,optionId} → {session:studentState}; сервер проверяет активный этап, тип блока и optionId; уникальность participant+block; повтор того же ответа идемпотентен, иной ответ при allowRepeat=false — 409
- GET /api/projection/{projectorToken} → {session:projectorState}; случайный read-only token, не teacher credential

teacherState: {id,revision,locale,currentStageId,document:teacherProjection,joinCode,projectorUrl,participants:[{id,name}],answers:[{participantId,blockId,optionId}]}. revision меняется при навигации учителя, а не от каждого ответа ученика. Закрытые данные только в этой авторизованной проекции.

studentState: {id,revision,locale,currentStageId,stage:studentStageProjection,ownAnswers:[{blockId,optionId}]}. projectorState аналогичен, без ownAnswers. Проектор/ученик не получают решения, заметки, owner keys, teacher URLs/токены, чужие ответы или полный storage document. Polling возвращает текущее состояние, вкладки не являются source of truth. Назад не сбрасывает ответы.

Models runtime: TeachingSession (UUID, released version FK, owner_key, locale, current_stage_id, revision, join_code unique, projector_token unique); SessionParticipant (UUID, session FK, name); SessionAnswer (session+participant+block unique, option_id). Credential участника — серверная связь cookie-session с participant, имя не является авторизацией. Состояния раздельны между занятиями. Таймер, reset/attempt, co-teaching и завершение/история — следующий этап.

## Ошибки и страницы

ApiProblem(problemCode,status) рендерится как {error:{code}}. Коды: invalid_document(422), invalid_media(422), revision_conflict(409), answer_locked(409), invalid_action(422), not_found(404). Стандартные Laravel 422 также обрабатываются клиентом. Raw SQL/credentials не выдаются.

Страницы /{locale}/studio (page studio), /{locale}/studio/lessons/{id} (editor, lessonId), /{locale}/teach/{id} (teacher, sessionId), /{locale}/join (join), /{locale}/participate/{id} (student, sessionId), /{locale}/project/{token} (projector, projectorToken). Общий Blade boot передаёт page/context, csrf и словарь trans('studio') дополнительно к interface. Учительские resource-page проверяют владение до отдачи HTML. Начальные UI ru/de, содержание — по BLOCK_CONTRACT.

Интерфейс: утверждённые paper/ink/amber tokens, скрываемое левое меню, кнопки изменения порядка вместо обязательного drag; ручное сохранение и явные ошибки/conflict; реальный preview renderer. Пульт stage2 минимальный, отделяемое окно и полные возможности этапа3 пока не выдаются за готовые.
