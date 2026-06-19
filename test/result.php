<?php
echo "\n=== RestrictionResult ===\n";

$r = TestCase::createGAC()->getRestrictions(false);
$h22 = strtotime(date('Y-m-d 22:00:00'));
$res = $r->run(['date' => ['timestamp' => $h22]]);

test('propiedades tipadas en fallo', function () use ($res) {
    assert($res->passed === false);
    assert($res->type === 'date');
    assert($res->rule === 'in_range');
    assert(is_int($res->restrictionId));
    assert(is_array($res->ruleConfig) && isset($res->ruleConfig['sd']));
    assert(is_array($res->context) && isset($res->context['timestamp']));
    assert(is_string($res->message));
});

test('jsonSerialize', function () use ($res) {
    $data = json_decode(json_encode($res), true);
    assert($data['passed'] === false && $data['type'] === 'date' && $data['rule'] === 'in_range');
    assert(isset($data['config'], $data['context'], $data['message'], $data['restriction_id']));
});

$h10 = strtotime(date('Y-m-d 10:00:00'));
$ok = $r->run(['date' => ['timestamp' => $h10]]);

test('passed true en exito', function () use ($ok) {
    assert($ok->passed === true);
    assert($ok->type === null && $ok->rule === null);
});
