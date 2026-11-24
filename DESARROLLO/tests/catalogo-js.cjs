const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.join(__dirname,'..');
let payload=JSON.parse(fs.readFileSync(path.join(root,'Web/assets/data/catalogo-rpv.json'),'utf8')),fail=false,count=0;
const sandbox={window:{addEventListener(){}},document:{querySelectorAll(){return[]}},console:{warn(){}},setTimeout,clearTimeout,AbortController,fetch:async()=>{if(fail)throw Error('Simulated outage');return{ok:true,json:async()=>structuredClone(payload)}}};
vm.runInNewContext(fs.readFileSync(path.join(root,'Web/assets/js/catalogo-rpv.js'),'utf8'),sandbox);
(async()=>{
 const api=sandbox.window.RPVCatalogo;await api.load();
 for(const letter of ['A','B','C']){ const price=api.get('caldera-tipo-'+letter.toLowerCase()).precio_texto;
  for(const q of [`Cuánto cuesta la caldera tipo ${letter}`,`precio modelo ${letter.toLowerCase()}`,`valor caldera ${letter}`]){assert.ok(api.answer(q).includes(price),q);count++;}
 }
 for(const q of ['precio tipo D','precio equipo 1','precio modelo 99']){assert.ok(api.answer(q).includes('correspondencia aprobada'));assert.ok(!api.answer(q).includes('$'));count++;}
 assert.ok(api.answer('precio de tinaja para 4 personas').includes(api.get('tinaja-4-personas').precio_texto));count++;
 assert.ok(api.answer('precio de tinaja para siete personas').includes(api.get('tinaja-7-personas').precio_texto));count++;
 assert.ok(api.answer('precio estufa').includes('Precio a consultar'));count++;
 assert.ok(api.answer('Qué tipo de tinaja me conviene').includes('Tinaja para 4 personas'));assert.ok(!api.answer('Qué tipo de tinaja me conviene').includes('Caldera tipo'));count++;
 assert.equal(api.answer('hola'),null);count++;
 payload.productos[0].precio_texto='$1.234.567 CLP';payload.productos[0].respuesta='Caldera tipo A: $1.234.567 CLP. Condiciones nuevas.';
 await api.load();assert.ok(api.answer('precio tipo A').includes('$1.234.567'));count++;
 payload.conocimiento='## Precio\nCaldera tipo A: $1.234.567 CLP.\n';payload.productos[0].vigente_hasta='2000-01-01';const expiredSnapshot=await api.load();assert.ok(!api.answer('precio tipo A').includes('$1.234.567'));assert.ok(!expiredSnapshot.conocimiento.includes('$1.234.567'));count++;
 fail=true;await api.load();assert.ok(api.answer('precio tipo A').includes('No pude consultar'));assert.ok(!api.answer('precio tipo A').includes('$'));count++;
 console.log(`OK: ${count} controles de resolución de productos, actualización y ausencia de catálogo.`);
})().catch(e=>{console.error(e);process.exitCode=1});
