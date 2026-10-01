# Этап 7: совместное проведение

Совместное проведение расширяет существующий runtime. Grant действует только для одного реального занятия, не предоставляет studio/history/authoring/media-owner доступа и никогда не создаёт alias owner identity. Репетиции не поддерживают приглашения.

## Доступ и DTO

Server session хранит `lesson_teacher_grants[sessionId]={grantId,proof}`. Invitation token и grant proof — независимые UUID, в БД только SHA256. Invitation по умолчанию действует час; `expiresInSeconds` допускает 60–86400 секунд. Одно принятие создаёт grant на четыре часа от принятия; чтение, повтор принятия и login срок не продлевают.

Новое действующее приглашение может заменить в той же cookie map истёкший или отозванный grant. Действующий установленный grant блокирует замену и не расходует новое приглашение; несовпадающий proof действующего grant возвращает generic404. Повтор уже принятого приглашения разрешён только при том же установленном действующем grant/proof, даже после expiry самого invitation; старое приглашение не восстанавливает доступ после expiry/revoke или замены grant.

Каждое чтение teacher/projector/media и каждая команда заново проверяет session, proof, expiry и revoke. Login/register сохраняют scoped grants через regeneration, logout/continueGuest/password-session invalidation очищают их. Verified claim сохраняет существующие grants, но не добавляет новые права. Grant не связан автоматически с аккаунтом или именем.

Actor DTO:

```json
{
  "session": {"id":"UUID","revision":1},
  "actor": {"kind":"owner|grant","isPresenter":false,"capabilities":["moderate"],"expiresAt":"ISO UTC, только grant"},
  "collaboration": {"controlEpoch":0,"presenter":{"kind":"owner|grant|vacant","grantId":"только grant","displayName":"только grant"}}
}
```

Capabilities: `present` разрешает begin/pause/resume/stage/timer/message/wave/block.open/close/reveal; `moderate` — answer.moderate/publish/unpublish, role.assign, signal.ack. Владелец всегда сохраняет `finish` и `manageCollaboration`. Когда ведёт grant, владелец может модерировать, завершить или явно вернуть ведение. Все авторизованные учителя видят заметки, решения и ответы; приглашение должно явно предупреждать об этом.

Grant DTO исключает bearer projector URL и owner media URLs. Media references всех stage projections grant используют только session-scoped `/media/conduct/{sessionId}/{assetId}/{versionId}`; сервер выдаёт bytes только для exact version активного этапа. Grant projector использует отдельную cookie-authorized projection, его URL не открывает bearer доступ.

## HTTP

| Endpoint | Тело / результат |
| --- | --- |
| GET `/api/studio/sessions/{id}/collaboration` | Owner Actor DTO, collaboration также содержит invitations и grants без секретов |
| POST `/api/studio/sessions/{id}/collaboration/commands` | `{commandId,expectedRevision,controlEpoch,action,payload}` → Actor DTO + acknowledgedCommandId |
| POST `/api/teacher-invitations/accept` | `{token,displayName}` → `{sessionId,grant:{id,displayName,expiresAt},teacherUrl}`; proof устанавливается только в server session |
| GET `/api/conduct/sessions/{id}` | Grant Actor DTO |
| POST `/api/conduct/sessions/{id}/commands` | Обычная runtime command с обязательным controlEpoch → Grant Actor DTO + acknowledgedCommandId |
| GET `/api/conduct/sessions/{id}/projection` | Public projector projection после проверки grant |
| GET `/media/conduct/{sessionId}/{assetId}/{versionId}` | Exact active-stage image bytes после проверки grant |

Страницы: `/{locale}/teacher-invitations#token=UUID`, `/{locale}/conduct/{sessionId}`, `/{locale}/conduct/{sessionId}/control`, `/{locale}/conduct/{sessionId}/projector`. Отдельный пульт сохраняет тот же scoped grant и права. UI удаляет fragment после чтения token. Grant proof не отправляется в JS/HTML/JSON.

Governance actions:

- `invite.create`: payload `{}` или `{expiresInSeconds}`. Только первый success содержит `invitation:{id,expiresAt,url}`. URL не сохраняется, receipt replay не выдаёт его повторно.
- `invite.revoke`: `{id}` отзывает invitation отдельно от уже выданного grant.
- `grant.revoke`: `{id}` отзывает grant. Если grant ведёт, presenter становится vacant, epoch увеличивается.
- `presenter.transfer`: `{grantId}` явно выбирает действующий grant этого занятия; epoch увеличивается.
- `presenter.reclaim`: `{}` явно возвращает owner, epoch увеличивается.

После finish запрещены create/accept/transfer/reclaim; owner может отзывать invitation/grant. Grant может читать завершённое занятие до своего фиксированного expiry. Истечение active grant оставляет vacant; автоматического перехода к owner нет.

## Атомарность и stale команды

Порядок: `OwnerMutation::forSession` → fresh owner mutex → session row lock → fresh actor/proof/revoke/expiry/capability → controlEpoch → actor-bound receipt/fingerprint/replay → expectedRevision → существующий runtime command → save/receipt. Claim не ломает scoped grant: owner mutex обнаруживается заново.

Transfer/reclaim/revoke active grant увеличивают epoch и revision атомарно. Actor receipt содержит kind/id/epoch. Старые owner receipts с null binding принимаются только owner при epoch0. Legacy owner command без controlEpoch действует только при epoch0; новые UI всегда передают epoch. Старая вкладка после передачи и обратного возврата не получает ACK старой команды. Уже отозванный grant не получает session даже в error response. Авторизованный конфликт409 содержит свежий Actor DTO, безопасный для данного actor.
