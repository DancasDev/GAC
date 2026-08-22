# Restricciones en GAC (ABAC)

Las restricciones permiten bloquear el acceso según condiciones del entorno de ejecución (IP, fecha/hora o dominio) sin modificar las reglas de permisos base.

---

## 1. Evaluación Básica de Restricciones

Para verificar si la petición actual está bloqueada por alguna regla contextual:

```php
$context = [
    'ip'   => ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'],
    'date' => ['timestamp' => time()]
];

if ($gac->isRestricted($context)) {
    // La petición está bloqueada por una regla de restricción activa
}
```

---

## 2. Reglas y Handlers Nativos

GAC incluye tres validadores de restricción por defecto:

### 2.1 Restricción por IP (`ip`)
- **`allow`**: Permite únicamente las IPs indicadas.
- **`deny`**: Bloquea las IPs indicadas.

```json
{ "list": ["192.168.1.50", "10.0.*.*"] }
```

### 2.2 Restricción por Horario o Fecha (`date`)
- **`in_range`**: Permite el acceso solo dentro del horario especificado.
- **`out_range`**: Bloquea el acceso durante la ventana especificada.
- **`by_day`**: Restringe por días de la semana (0 = Domingo, 1 = Lunes...).

```json
{ "sd": "%Y-%M-%D 08:00", "ed": "%Y-%M-%D 18:00" }
```

### 2.3 Restricción por Dominio (`domain`)
- **`allow`**: Permite solo dominios o subdominios específicos.
- **`deny`**: Bloquea dominios específicos.

```json
{ "list": ["admin.miempresa.com", "*.miempresa.com"] }
```

---

## 3. Diagnóstico Detallado de Bloqueos

Si necesitas conocer la razón exacta por la que se denegó el acceso para construir una respuesta o mensaje de error:

```php
$result = $gac->getRestrictionResult($context);

if (!$result->passed) {
    echo $result->type;    // ej: 'ip'
    echo $result->rule;    // ej: 'allow'
    echo $result->message; // ej: 'IP address 200.0.0.1 is not in whitelist'
}
```

---

## 4. Crear un Handler Personalizado

Puedes añadir nuevos tipos de restricción implementando `RestrictionHandlerInterface` y registrando la clase:

```php
use DancasDev\GAC\Restrictions\Restrictions;

Restrictions::register('dispositivo', MiValidadorDispositivo::class);
```
