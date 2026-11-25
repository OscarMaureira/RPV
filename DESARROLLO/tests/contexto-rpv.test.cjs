'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const contextApi = require('../Web/assets/js/contexto-rpv.js');

const markdown = fs.readFileSync(path.join(__dirname, '../Web/assets/data/conocimiento-rpv.md'), 'utf8');
const knowledge = contextApi.createKnowledgeBase(markdown);
const make = scope => contextApi.create({ scope, storage: null });
const say = (manager, message) => manager.process(message, { knowledge });

const tests = [];
function test(name, fn) { tests.push({ name, fn }); }

test('saludo único', () => {
  const result = say(make(), 'Holaaa');
  assert.equal(result.intencion, 'saludo');
  assert.match(result.respuesta, /^¡Hola!/);
  assert.doesNotMatch(result.respuesta, /No encuentro/);
});

test('error ortográfico frecuente en saludo', () => {
  const result = say(make(), 'Hla');
  assert.equal(result.intencion, 'saludo');
});

test('saludo más consulta produce una sola respuesta útil', () => {
  const result = say(make(), 'Hola, quiero saber de los productos');
  assert.match(result.respuesta, /^¡Hola! ¡Claro!/);
  assert.match(result.respuesta, /Calderas[\s\S]*Tinajas[\s\S]*Estufas/);
});

test('catálogo y selección de tinajas', () => {
  const manager = make();
  say(manager, 'Quiero saber de los productos');
  const result = say(manager, 'Tinajas');
  assert.match(result.respuesta, /4 y 7 personas/);
  assert.equal(result.estado.productoActual, 'tinaja');
  assert.equal(result.estado.unidadEsperada, 'personas');
});

test('6 se interpreta como personas y sugiere modelo de 7', () => {
  const manager = make();
  say(manager, 'Tinajas');
  const result = say(manager, 'unas seis personas');
  assert.equal(result.estado.datosCliente.personas, 6);
  assert.equal(result.estado.modeloActual, 'tinaja-7-personas');
  assert.match(result.respuesta, /7 personas/);
});

test('1500 se interpreta como litros', () => {
  const manager = make();
  say(manager, 'Quiero información de calderas');
  const result = say(manager, '1500');
  assert.equal(result.estado.datosCliente.litros, 1500);
  assert.equal(result.estado.productoActual, 'caldera');
  assert.match(result.respuesta, /1\.500 litros/);
});

test('65 se interpreta como metros cuadrados', () => {
  const manager = make();
  say(manager, 'Estufas');
  const result = say(manager, '65');
  assert.equal(result.estado.datosCliente.metrosCuadrados, 65);
  assert.match(result.respuesta, /65 m²/);
});

test('cantidad de productos no se confunde con personas', () => {
  const manager = make();
  say(manager, 'Quiero cotizar varias tinajas');
  const result = say(manager, '3');
  assert.equal(result.estado.datosCliente.cantidadProductos, 3);
  assert.equal(result.estado.datosCliente.personas, null);
});

test('unidad explícita contradictoria pide aclaración y no guarda', () => {
  const manager = make();
  say(manager, 'Tinajas');
  const result = say(manager, '1500 litros');
  assert.equal(result.estado.datosCliente.personas, null);
  assert.match(result.respuesta, /había preguntado por personas/);
});

test('sí ofrece precio del modelo desde el Markdown', () => {
  const manager = make();
  say(manager, 'Tinajas');
  say(manager, '6');
  const result = say(manager, 'Sí');
  assert.equal(result.intencion, 'precio');
  assert.match(result.respuesta, /precio/i);
  assert.equal(result.estado.preguntaPendiente, null);
});

test('la grande mantiene producto y modelo', () => {
  const manager = make();
  say(manager, 'Tinajas');
  const result = say(manager, 'La grande');
  assert.equal(result.estado.modeloActual, 'tinaja-7-personas');
  assert.match(result.respuesta, /7 personas|2,00 m/);
});

test('despacho conserva producto y solicita destino según conocimiento', () => {
  const manager = make();
  say(manager, 'Tinajas');
  const result = say(manager, 'Y el despacho');
  assert.equal(result.estado.productoActual, 'tinaja');
  assert.equal(result.intencion, 'despacho');
  assert.match(result.respuesta, /comuna|región|destino/i);
});

test('precio de calderas y reformulación redundante son coherentes', () => {
  const manager = make();
  const first = say(manager, 'Cuánto cuestan las calderas');
  const second = say(manager, 'Qué valor tienen las calderas');
  assert.equal(first.intencion, 'precio');
  assert.equal(second.estado.productoActual, 'caldera');
  assert.equal(second.estado.repeticiones, 1);
  assert.match(second.respuesta, /confirmo nuevamente/);
});

test('cambio de tinajas a estufas reemplaza contexto pendiente', () => {
  const manager = make();
  say(manager, 'Tinajas');
  const result = say(manager, 'Me interesan las estufas');
  assert.equal(result.estado.productoActual, 'estufa');
  assert.equal(result.estado.unidadEsperada, 'm2');
});

test('número ambiguo pide unidad', () => {
  const result = say(make(), '42');
  assert.match(result.respuesta, /personas, litros, metros cuadrados/);
});

test('dos intenciones se resuelven en una respuesta principal', () => {
  const result = say(make(), 'Precio y despacho de calderas');
  assert.equal(result.intencion, 'precio_despacho');
  assert.match(result.respuesta, /Sobre el despacho/);
  assert.equal(result.estado.preguntaPendiente, 'ubicacion_despacho');
});

test('fallo de conocimiento no inventa información', () => {
  const manager = make();
  const result = manager.process('Cuánto cuestan las calderas', { knowledge: null });
  assert.match(result.respuesta, /No encuentro información suficiente|confirmarlo con el equipo/i);
});

test('controladores serializan envíos rápidos', () => {
  const main = fs.readFileSync(path.join(__dirname, '../Web/assets/js/asistente-rpv.js'), 'utf8');
  const boilers = fs.readFileSync(path.join(__dirname, '../Web/assets/js/asistente-rpv-calderas.js'), 'utf8');
  assert.match(main, /sendQueue=sendQueue\.then/);
  assert.match(boilers, /sendQueue=sendQueue\.then/);
});

test('interfaz interior conserva reglas móviles', () => {
  const css = fs.readFileSync(path.join(__dirname, '../Web/assets/css/asistente-rpv.css'), 'utf8');
  assert.match(css, /@media\(max-width:767px\)/);
  assert.match(css, /100dvh|34dvh/);
});

test('mensaje vacío se ignora', () => {
  const result = say(make(), '   ');
  assert.equal(result.ignorado, true);
});

test('limpiar restablece por completo el estado', () => {
  const manager = make();
  say(manager, 'Tinajas');
  say(manager, '6');
  const state = manager.reset();
  assert.equal(state.productoActual, null);
  assert.equal(state.datosCliente.personas, null);
  assert.equal(state.historial.length, 0);
});

test('restaura sesión compatible sin guardar ubicación ni texto crudo', () => {
  const memory = new Map();
  const storage = { getItem: key => memory.get(key) || null, setItem: (key, value) => memory.set(key, value), removeItem: key => memory.delete(key) };
  const first = contextApi.create({ storage, storageKey: 'test' });
  say(first, 'Tinajas');
  say(first, '6');
  const second = contextApi.create({ storage, storageKey: 'test' });
  assert.equal(second.getState().datosCliente.personas, 6);
  assert.doesNotMatch(memory.get('test'), /coordenad|latitud|longitud/i);
});

test('Markdown crea una base con secciones comerciales', () => {
  assert.ok(knowledge.sections.length > 100);
  assert.match(knowledge.ask('¿Cuáles son los precios de las calderas?', 'caldera'), /\$/);
});

let failures = 0;
for (const item of tests) {
  try { item.fn(); console.log('✓', item.name); }
  catch (error) { failures++; console.error('✗', item.name); console.error(error.stack); }
}
console.log(`\n${tests.length - failures}/${tests.length} pruebas aprobadas`);
if (failures) process.exitCode = 1;
