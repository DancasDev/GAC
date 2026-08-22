# Guía: Validación y Detección de Colisiones (`Utils`)

La clase `DancasDev\GAC\Utils` proporciona utilidades estáticas para validar la sintaxis de scopes y detectar posibles duplicados en la base de datos antes de registrar o editar registros.

---

## 1. Validación Sintáctica: `Utils::scopeValidate`

Comprueba si una cadena cumple con las reglas de formato de scopes en GAC:

```php
use DancasDev\GAC\Utils;

$isValid = Utils::scopeValidate('/sucursal/caracas,/sucursal/maracaibo'); // true
```

### Reglas Principales:

| Formato | Ejemplo | Válido | Motivo |
|---|---|---|---|
| **NULL** | `null` | Sí | Indica herencia dinámica. |
| **Wildcard Global** | `"*"` | Sí | Alcance a todo el sistema. |
| **Ruta Simple** | `"/sucursal/1"` | Sí | Ruta jerárquica. |
| **Multi-Path** | `"/a/1,/b/2"` | Sí | Rutas separadas por comas. |
| **Wildcard Prefijo** | `"/sucursal/*"` | Sí | Cubre la ruta y sub-niveles. |
| **String Vacío** | `""` | No | Cadenas vacías no permitidas. |
| **Wildcard sin Prefijo** | `"/*"` | No | Se debe usar `"*"` para global. |
| **Doble Slash** | `"//sucursal"` | No | Segmento vacío. |
| **Trailing Slash** | `"/sucursal/"` | No | No debe terminar en `/`. |
| **Wildcard Intermedio** | `"/sucursal/*/depto"` | No | Wildcards solo al final. |
| **Rutas Duplicadas** | `"/sucursal/1,/sucursal/1"` | No | Rutas repetidas en la misma cadena. |

---

## 2. Detección de Colisiones en BD: `Utils::scopeHasCollision`

Dado que `scope_path` permite almacenar listas de rutas separadas por comas, un índice `UNIQUE` en la base de datos compara cadenas completas y no puede validar si una ruta individual ya está presente en otra fila.

`Utils::scopeHasCollision` evalúa si alguna de las rutas del nuevo scope ya existe en los registros actuales de la base de datos.

```php
public static function scopeHasCollision(
    array|string|null $existingRecords, 
    ?string $newScopePath
): bool
```

### Formatos Aceptados:

1. **Arreglo Asociativo de BD (`PDO::fetchAll`):**
   ```php
   $dbRows = [
       ['id' => 1, 'scope_path' => '/sucursal/caracas,/sucursal/maracaibo'],
       ['id' => 2, 'scope_path' => '/sucursal/valencia']
   ];
   ```
2. **Arreglo de Strings:**
   ```php
   $existingScopes = ['/sucursal/caracas,/sucursal/maracaibo', '/sucursal/valencia'];
   ```
3. **String Único o NULL:**
   ```php
   $existingScope = '/sucursal/caracas';
   ```

---

## 3. Ejemplo de Uso Previo a Inserción

```php
use DancasDev\GAC\Utils;

$entityType = '1';
$entityId   = 10;
$moduleId   = 3;
$newScope   = '/sucursal/caracas';
$feature    = 7;

// 1. Validar sintaxis
if (!Utils::scopeValidate($newScope)) {
    throw new \InvalidArgumentException("Sintaxis de scope inválida.");
}

// 2. Obtener registros existentes de la BD para esa entidad y módulo
$stmt = $pdo->prepare("
    SELECT scope_path 
    FROM gac_permission 
    WHERE entity_type = ? AND entity_id = ? AND module_id = ? AND deleted_at IS NULL
");
$stmt->execute([$entityType, $entityId, $moduleId]);
$existingRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// 3. Comprobar si la ruta ya está asignada
if (Utils::scopeHasCollision($existingRecords, $newScope)) {
    throw new \DomainException("La ruta '$newScope' ya está registrada para este módulo.");
}

// 4. Proceder con el INSERT
$insert = $pdo->prepare("
    INSERT INTO gac_permission (entity_type, entity_id, module_id, scope_path, feature, level)
    VALUES (?, ?, ?, ?, ?, '1')
");
$insert->execute([$entityType, $entityId, $moduleId, $newScope, $feature]);
```
