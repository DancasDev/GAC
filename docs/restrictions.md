# Restricciones

Las restricciones limitan el acceso según condiciones externas: **fecha y hora**,
**dirección IP** o **dominio**. Se definen por tipo (`date`, `ip`, `domain`) y regla (`before`, `allow`, etc.).

---

## 1. La tabla

```php
use DancasDev\GAC\Schema;
Schema::install($pdo); // Crea gac_restriction junto con las demás tablas
```

### `gac_restriction`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Identificador |
| `entity_type` | ENUM('0','1','2','3') | `'0'`=rol, `'1'`=usuario, `'2'`=cliente, `'3'`=global |
| `entity_id` | INT | ID de la entidad. `0` cuando es global |
| `scope_path` | VARCHAR(255) | Alcance (misma lógica que permisos) |
| `type` | VARCHAR(30) | Tipo: `date`, `ip`, `domain` |
| `rule` | VARCHAR(30) | Regla según el tipo |
| `config` | LONGTEXT | JSON con los parámetros de la regla |
| `is_disabled` | ENUM('0','1') | `'0'` = activo |

---

## 2. Prioridad y scope

Misma lógica que en [permisos](permissions.md):

| Fuente | Prioridad | Cuándo aplica |
|--------|-----------|---------------|
| Personal (`entity_type` coincide con la entidad) | `-1` (máxima) | Siempre que exista |
| Rol (`entity_type='0'`) | `priority` de `gac_role_entity` | Si no hay restricción personal |
| Global (`entity_type='3'`) | Último recurso | Si no hay restricción personal ni de rol para ese `type` |

**Solo UNA restricción por `type`** sobrevive: la de mayor prioridad. Si un usuario
tiene una restricción `date` personal, las restricciones `date` de su rol y las
globales **se ignoran** para ese usuario.

---

## 3. Restricción `date`

Controla acceso según fecha y hora.

| Regla | ¿Cuándo deniega? | `config` |
|-------|------------------|----------|
| `before` | La fecha actual **es anterior** a `d` | `{"d":"2026-01-01"}` |
| `after` | La fecha actual **es posterior** a `d` | `{"d":"2026-01-01"}` |
| `in_range` | El timestamp **NO está** entre `sd` y `ed` | `{"sd":"%Y-%M-%D 08:00","ed":"%Y-%M-%D 18:00"}` |
| `out_range` | El timestamp **SÍ está** entre `sd` y `ed` | `{"sd":"%Y-%M-%D 12:00","ed":"%Y-%M-%D 14:00"}` |
| `by_day` | El día de la semana **NO está** en la lista `d` | `{"d":["0","1","2","3","4","5","6"]}` (0=domingo, 6=sábado) |

### Comodines de fecha

Los placeholders `%Y`, `%M`, `%D` se reemplazan automáticamente por el año, mes y
día **actuales** al momento de validar. Esto permite definir horarios recurrentes
sin hardcodear fechas.

> Los placeholders funcionan con **todas** las reglas de `date`. En `before`/`after`,
> `{"d":"%Y-%M-%D"}` equivale a "hoy a las 00:00".

### Ejemplos

```sql
-- Nadie accede antes del 1 de enero de 2026
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'date', 'before', '{"d":"2026-01-01"}');

-- Solo se puede acceder entre 8am y 6pm (todos los días)
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'date', 'in_range', '{"sd":"%Y-%M-%D 08:00","ed":"%Y-%M-%D 18:00"}');

-- Bloquear acceso durante el almuerzo (12:00 a 14:00)
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'date', 'out_range', '{"sd":"%Y-%M-%D 12:00","ed":"%Y-%M-%D 14:00"}');

-- El usuario 10 no puede acceder después del 1 de julio de 2026
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('1', 10, '*', 'date', 'after', '{"d":"2026-07-01"}');

-- Solo fines de semana (domingo y sábado)
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'date', 'by_day', '{"d":["0","6"]}');
```

> Cuando usa `in_range`, **deniega** si la hora está **fuera** del rango.
> Cuando usa `out_range`, **deniega** si la hora está **dentro** del rango.

### Validar estructura antes de insertar

```php
// before / after: requiere d (string con fecha)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'before', ['d' => '2026-01-01']);         // ['d' => '2026-01-01']
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'after',  ['d' => '2026-01-01']);         // ['d' => '2026-01-01']
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'before', []);                             // false (falta d)

// in_range / out_range: requiere sd y ed (strings con fecha)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'in_range', ['sd' => '08:00', 'ed' => '18:00']);  // ['sd' => '08:00', 'ed' => '18:00']
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'out_range', ['sd' => '12:00', 'ed' => '14:00']); // ['sd' => '12:00', 'ed' => '14:00']
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'in_range', ['sd' => '08:00']);                // false (falta ed)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'out_range', ['ed' => '14:00']);               // false (falta sd)

// by_day: requiere d (array de strings "0" a "6")
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'by_day', ['d' => ['1','2','3','4','5']]);  // ['d' => ['1','2','3','4','5']] (lunes a viernes)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'by_day', ['d' => ['0','6']]);              // ['d' => ['0','6']] (solo finde)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'by_day', ['d' => []]);                     // false (array vacío)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'by_day', ['d' => ['0','7']]);              // false (7 no es válido)
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'by_day', []);                             // false (falta d)

// También filtra keys extra que no pertenecen a la regla
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'before', ['d' => '2026-01-01', 'extra' => 'x']);  // ['d' => '2026-01-01']
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('date', 'by_day', ['d' => ['0'], 'extra' => 'x']);         // ['d' => ['0']]
```

---

## 4. Restricción `ip`

Controla acceso según dirección IP.

| Regla | ¿Cuándo deniega? |
|-------|------------------|
| `allow` | La IP del usuario **NO está** en la lista |
| `deny` | La IP del usuario **SÍ está** en la lista |

### Estructura de la lista

`config` es un JSON con un array `list` de patrones IP. Puede usar wildcards (`*`).

```json
{"list": ["192.168.1.*", "10.0.*.*"]}
```

- `"192.168.1.*"` → cubre `192.168.1.0` a `192.168.1.255`
- `"10.0.*.*"` → cubre toda la red `10.0.0.0/16`
- `"200.1.1.50"` → IP exacta (sin wildcard)
- IPs inválidas (ej: `"not-an-ip"`) → **siempre denegadas**

### Ejemplos

```sql
-- Solo permitir IPs de la red local (whitelist)
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'ip', 'allow', '{"list":["192.168.1.*","10.0.*.*"]}');

-- Bloquear un rango específico (blacklist)
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('1', 5, '*', 'ip', 'deny', '{"list":["10.0.5.*"]}');
```

### Validar estructura

```php
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('ip', 'allow', ['list' => ['192.168.1.*']]);    // ['list' => ['192.168.1.*']]
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('ip', 'allow', ['list' => 'no-es-array']);      // false
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('ip', 'invalid_rule', ['list' => []]);          // false
```

---

## 5. Restricción `domain`

Controla acceso según el dominio (hostname) desde donde se hace la solicitud HTTP.

| Regla | ¿Cuándo deniega? |
|-------|------------------|
| `allow` | El host **NO está** en la lista |
| `deny` | El host **SÍ está** en la lista |

### Estructura de la lista

`config` es un JSON con un array `list` de patrones de dominio. Puede usar wildcards (`*`).

```json
{"list": ["*.miepresa.com", "localhost"]}
```

- `"*.miepresa.com"` → coincide con `admin.miepresa.com`, `api.miepresa.com`, etc.
- `"*"` → coincide con cualquier dominio
- `"localhost"` → solo localhost (útil en desarrollo)
- `"admin.miepresa.com"` → dominio exacto (sin wildcard)

### Ejemplos

```sql
-- Solo permitir dominios corporativos
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('3', 0, '*', 'domain', 'allow', '{"list":["*.miepresa.com","localhost"]}');

-- Bloquear un dominio específico
INSERT INTO gac_restriction (entity_type, entity_id, scope_path, type, rule, config)
VALUES ('1', 5, '*', 'domain', 'deny', '{"list":["baneado.ejemplo.com"]}');
```

### Validar estructura

```php
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'allow', ['list' => ['*.miepresa.com']]);  // ['list' => ['*.miepresa.com']]
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'allow', ['list' => 'no-es-array']);       // false
\DancasDev\GAC\Restrictions\Restrictions::validateStructure('domain', 'invalid_rule', ['list' => []]);           // false
```

---

## 6. Usarlo en PHP

```php
$gac = new GAC($pdo);
$gac->setEntity('user', 30)->setScope('*');

$r = $gac->getRestrictions();

// Verificar restricción date
$hoy = time();
$resultado = $r->run(['date' => ['timestamp' => $hoy]]);

// Verificar restricción ip
$resultado = $r->run(['ip' => ['ip' => '192.168.1.50']]);

// Verificar restricción domain
$resultado = $r->run(['domain' => ['host' => $_SERVER['HTTP_HOST']]]);

// El resultado indica todo
if ($resultado->passed) {
    // Acceso permitido, no hay restricciones que lo bloqueen
} else {
    echo $resultado->type;          // "date" | "ip" | "domain"
    echo $resultado->rule;          // "in_range" | "allow" | "deny" | etc.
    echo $resultado->message;       // "Acceso denegado: fuera del rango horario"
    echo $resultado->restrictionId; // ID del registro que bloqueó

    // Datos completos del bloqueo (útil para logs)
    $data = json_encode($resultado);
    // {
    //   "passed": false,
    //   "type": "date",
    //   "rule": "in_range",
    //   "config": {"sd":"...","ed":"..."},
    //   "context": {"timestamp": 1718300000},
    //   "restriction_id": 42,
    //   "message": "Acceso denegado por restricción date"
    // }
}
```

---

## 7. Caché y purga

```php
// Al igual que con permisos, las restricciones se cachean
$gac = new GAC($pdo, ['dir' => __DIR__ . '/cache']);

// Purgar caché de un usuario
$gac->purgeCacheBy('user', [30]);

// Purgar caché de un rol
$gac->purgeCacheBy('role', [1]);

// Purgar caché de restricciones globales
$gac->purgeCacheBy('global');

// Limpiar todo el caché
$gac->clearCache(true);
```

---

## Errores comunes

| Error | Causa | Solución |
|-------|-------|----------|
| La restricción no se aplica y todo pasa | La restricción global es reemplazada por una personal o de rol | Solo una restricción por `type` gana. Si el usuario tiene `date` personal, la global `date` se ignora |
| Se insertó una restricción nueva pero el usuario sigue sin tenerla | El caché aún no expiró | Llame a `purgeCacheBy('user', [id])`, `purgeCacheBy('role', [id])` o `purgeCacheBy('global')` para forzar la recarga |
| `in_range` deja pasar a las 22:00 | Está interpretando mal: `in_range` **deniega** lo que está FUERA del rango | A las 22:00 (fuera de 08-18) → deniega. Correcto |
| `out_range` bloquea a las 10:00 | `out_range` **deniega** lo que está DENTRO del rango | Si tu rango es 12-14, a las 10:00 (fuera) → permite |
| `by_day` con `["0"]` deniega los lunes | `by_day` es allow-list: solo los días listados están permitidos | Si querés bloquear solo los lunes, listá los otros 6 días |
| IP con wildcard no matchea | El wildcard solo funciona si el patrón contiene `*` | `"192.168.1.*"` genera un regex automáticamente. `"192.168.1.50"` sin `*` hace match exacto |
| Dominio con wildcard no matchea | `*` solo coincide con subdominios, no con el dominio base | `"*.ejemplo.com"` no cubre `ejemplo.com`, necesitás agregarlo explícitamente: `["*.ejemplo.com", "ejemplo.com"]` |
| `validateStructure` devuelve `false` | Te falta un campo obligatorio en el `config` | Cada regla tiene sus campos requeridos: `before`/`after` necesitan `d`, `in_range`/`out_range` necesitan `sd` y `ed`, `by_day` necesita `d` como array con valores `"0"`–`"6"`, `allow`/`deny` necesitan `list` como array |
