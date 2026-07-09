/**
 * SISFAL — JavaScript principal (SPA)
 * Instituto Nacional Tecnico Industrial
 *
 * CONFIGURACION: cambia BASE_API a la URL donde instalas el sistema
 * Ejemplo local:   'http://localhost/sisfal/app/controllers'
 * Ejemplo remoto:  'https://tudominio.com/sisfal/app/controllers'
 */
const BASE_API = './app/controllers';
 
/* ============================================================
   ================  LISTAS DE FILTROS (EDITAR AQUÍ)  ===========
   ============================================================
   Estas listas alimentan los <select> de "Especialidad" y
   "Sección" en la página de Estudiantes. Para agregar, quitar o
   renombrar una especialidad o sección, edita únicamente estos
   arreglos; el resto del código los usa automáticamente.
 
   El filtrado se hace a partir del campo "codigo" del estudiante
   (ej: "DS3A" = especialidad DS + año 3 + sección A), así que el
   valor `prefijo` de cada especialidad debe coincidir con las
   letras iniciales que usas en el campo Código al registrar
   estudiantes.
   ============================================================ */
const ESPECIALIDADES = [
  { prefijo: 'MA',   nombre: 'Mantenimiento Automotriz' },
  { prefijo: 'DS',   nombre: 'Desarrollo de Software' },
  { prefijo: 'MI',   nombre: 'Mecánica Industrial' },
  { prefijo: 'ITSI', nombre: 'Infraestructura Tecnológica y Sistemas Informáticos' },
  { prefijo: 'ECA',  nombre: 'Electrónica' },
  { prefijo: 'SE',   nombre: 'Sistemas Eléctricos' },
  // <-- agrega aquí nuevas especialidades: { prefijo:'XX', nombre:'...' },
];
 
const SECCIONES = ['A', 'B', 'C', 'D'];
// <-- agrega/quita letras de sección aquí, ej: 'E', 'F'...
 
const ANIO_MIN = 1;
const ANIO_MAX = 3;
// <-- si el instituto agrega un 4to año, cambia ANIO_MAX a 4.
 
const ESTUDIANTES_POR_PAGINA = 10;

/* ============================================================ */
 
/* ============================================================
   UTILIDADES
   ============================================================ */
const toast = (msg, tipo='exito') => {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className   = tipo;
  el.style.display = 'block';
  setTimeout(() => el.style.display='none', 3200);
};
 
const esc = (s) => String(s ?? '').replace(/</g,'&lt;');
 
async function api(controller, params={}, body=null, method='GET') {
  const qs  = new URLSearchParams(params).toString();
  const url = `${BASE_API}/${controller}?${qs}`;
  const opt = { method, headers: { 'Content-Type':'application/json' } };
  if (body) opt.body = JSON.stringify(body);
  const res = await fetch(url, opt);
  let data;
  try { data = await res.json(); }
  catch { throw new Error('Respuesta inválida del servidor (revisa que el controlador no tenga errores PHP).'); }
  if (!res.ok) throw new Error(data.error || 'Error del servidor');
  return data;
}
 
/* ============================================================
   ROUTER / NAVEGACION (nav superior)
   ============================================================ */
const navLinks = document.querySelectorAll('#nav-principal [data-page]');
const paginas   = document.querySelectorAll('.pagina');
 
function navegar(pageId) {
  paginas.forEach(p => p.classList.remove('activa'));
  navLinks.forEach(l => l.classList.remove('active'));
  const pag = document.getElementById('pag-' + pageId);
  if (pag) pag.classList.add('activa');
 
  // Resalta el item correspondiente del nav (o "Menú Principal" si
  // la página vive dentro del dropdown).
  const linkDirecto = document.querySelector(`#nav-principal > [data-page="${pageId}"]`);
  if (linkDirecto) {
    linkDirecto.classList.add('active');
  } else if (pageId === 'sanciones' || pageId === 'historial') {
    document.getElementById('btn-menu-principal').classList.add('active');
  }
 
  document.getElementById('dropdown-menu').classList.remove('abierto');
  manejadoresPagina[pageId]?.();
}
 
navLinks.forEach(l => l.addEventListener('click', e => {
  e.preventDefault();
  navegar(l.dataset.page);
}));
 
/* Dropdown "Menú Principal" */
const dropdownMenu = document.getElementById('dropdown-menu');
document.getElementById('btn-menu-principal').addEventListener('click', (e) => {
  e.stopPropagation();
  dropdownMenu.classList.toggle('abierto');
});
 
/* Dropdown de usuario */
const menuUsuario = document.getElementById('menu-usuario');
document.getElementById('btn-user-menu').addEventListener('click', (e) => {
  e.stopPropagation();
  menuUsuario.classList.toggle('abierto');
});
document.addEventListener('click', () => {
  dropdownMenu.classList.remove('abierto');
  menuUsuario.classList.remove('abierto');
});

/* ============================================================
   AUTENTICACION
   ============================================================ */
async function checkSesion() {
  try {
    const d = await api('AuthController.php', {action:'check'});
    if (d.autenticado) {
      aplicarUsuario(d.nombre, d.rol);
      mostrarApp();
    } else {
      mostrarLogin();
    }
  } catch { mostrarLogin(); }
}
 
function aplicarUsuario(nombre, rol) {
  document.querySelector('.nombre-usuario').textContent = nombre;
  document.querySelector('.rol-usuario').textContent = rol
    ? rol.charAt(0).toUpperCase() + rol.slice(1)
    : '';
  document.getElementById('dash-bienvenida').textContent = `Bienvenido, ${nombre}`;
}
 
function mostrarLogin() {
  document.getElementById('pantalla-login').style.display = 'flex';
  document.getElementById('app').classList.remove('visible');
}
function mostrarApp() {
  document.getElementById('pantalla-login').style.display = 'none';
  document.getElementById('app').classList.add('visible');
  navegar('dashboard');
}
 
document.getElementById('form-login').addEventListener('submit', async e => {
  e.preventDefault();
  const err = document.getElementById('login-error');
  err.style.display = 'none';
  const btn = e.target.querySelector('button');
  btn.disabled = true; btn.textContent = 'Verificando...';
  try {
    const d = await api('AuthController.php', {action:'login'}, {
      usuario:    document.getElementById('l-usuario').value.trim(),
      contrasena: document.getElementById('l-password').value,
    }, 'POST');
    aplicarUsuario(d.nombre, d.rol);
    mostrarApp();
  } catch(ex) {
    err.textContent = ex.message;
    err.style.display = 'block';
  } finally {
    btn.disabled = false; btn.textContent = 'Iniciar Sesión';
  }
});
 
document.getElementById('btn-logout').addEventListener('click', async () => {
  if (!confirm('¿Está seguro que desea cerrar la sesión?')) return;
  await api('AuthController.php', {action:'logout'});
  mostrarLogin();
});
/* ============================================================ 
   DASHBOARD
============================================================ */
const ICONO_ESTUDIANTES = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`;
const ICONO_CLIPBOARD    = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="16" y2="15"/></svg>`;
const ICONO_SHIELD       = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>`;
const ICONO_BARCHART     = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>`;
const ICONO_CALENDAR     = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`;
const ICONO_CLOCK        = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`;
 
const ILUSTRACION_INSTITUTO = `
<svg viewBox="0 0 500 160" width="100%" style="max-width:640px" xmlns="http://www.w3.org/2000/svg">
  <ellipse cx="250" cy="152" rx="230" ry="6" fill="#E7F1EA"/>
  <g fill="#DCEBE1"><circle cx="70" cy="120" r="26"/><circle cx="95" cy="130" r="20"/><circle cx="430" cy="120" r="26"/><circle cx="405" cy="130" r="20"/></g>
  <g stroke="#4C8863" stroke-width="3" fill="none"><line x1="250" y1="18" x2="250" y2="40"/></g>
  <polygon points="250,18 280,34 250,34" fill="#4C8863"/>
  <polygon points="160,58 250,20 340,58" fill="#DCEBE1"/>
  <rect x="175" y="58" width="150" height="80" fill="#F3F6F4" stroke="#DCEBE1" stroke-width="2"/>
  <circle cx="250" cy="78" r="12" fill="none" stroke="#4C8863" stroke-width="2"/>
  <line x1="250" y1="78" x2="250" y2="71" stroke="#4C8863" stroke-width="2" stroke-linecap="round"/>
  <line x1="250" y1="78" x2="255" y2="78" stroke="#4C8863" stroke-width="2" stroke-linecap="round"/>
  <g fill="#DCEBE1"><rect x="190" y="100" width="16" height="20"/><rect x="216" y="100" width="16" height="20"/><rect x="268" y="100" width="16" height="20"/><rect x="294" y="100" width="16" height="20"/></g>
  <rect x="240" y="108" width="20" height="30" fill="#4C8863"/>
</svg>`;
 
async function cargarDashboard() {
  const cont = document.getElementById('dash-contenido');
  cont.innerHTML = `<div class="loading"><div class="spinner"></div>Cargando estadísticas...</div>`;
  try {
    const d = await api('DashboardController.php');
 
    cont.innerHTML = `
      <div class="stats-grid">
        <div class="stat-card">
          <div class="icono-circulo">${ICONO_ESTUDIANTES}</div>
          <h3>Estudiantes registrados</h3>
          <div class="numero">${d.estudiantes.toLocaleString()}</div>
          <div class="subt">${ICONO_ESTUDIANTES.replace('width="24"','width="14"')}Total de estudiantes activos</div>
        </div>
        <div class="stat-card">
          <div class="icono-circulo">${ICONO_CLIPBOARD}</div>
          <h3>Faltas del mes</h3>
          <div class="numero">${d.faltas_mes}</div>
          <div class="subt">${ICONO_CALENDAR}Faltas registradas este mes</div>
        </div>
        <div class="stat-card acento">
          <div class="icono-circulo">${ICONO_SHIELD}</div>
          <h3>Sanciones activas</h3>
          <div class="numero">${d.sanciones_activas}</div>
          <div class="subt">${ICONO_SHIELD}Sanciones en proceso</div>
        </div>
        <div class="stat-card">
          <div class="icono-circulo">${ICONO_BARCHART}</div>
          <h3>Faltas totales</h3>
          <div class="numero">${d.faltas_total}</div>
          <div class="subt">${ICONO_CLOCK}Faltas registradas en total</div>
        </div>
      </div>
 
      <div class="paneles-grid">
        <div class="panel-card">
          <div class="panel-titulo">Faltas por gravedad</div>
          <table>
            <thead><tr><th>Gravedad</th><th>Total</th></tr></thead>
            <tbody>
              ${d.por_gravedad.map(r => `<tr>
                <td><span class="badge badge-${r.gravedad}">${r.gravedad}</span></td>
                <td>${r.total}</td>
              </tr>`).join('') || '<tr><td colspan="2" class="tabla-vacia">Sin registros</td></tr>'}
            </tbody>
          </table>
        </div>
        <div class="panel-card">
          <div class="panel-titulo">Últimas faltas registradas</div>
          <table>
            <thead><tr><th>Estudiante</th><th>Tipo</th><th>Fecha</th></tr></thead>
            <tbody>
              ${d.ultimas_faltas.length
                ? d.ultimas_faltas.map(r => `<tr>
                    <td>${esc(r.estudiante)}</td>
                    <td>${esc(r.tipo)}</td>
                    <td>${esc(r.fecha)}</td>
                  </tr>`).join('')
                : '<tr><td colspan="3" class="tabla-vacia">Sin registros</td></tr>'
              }
            </tbody>
          </table>
        </div>
      </div>
 
      <div class="ilustracion-wrap">${ILUSTRACION_INSTITUTO}</div>`;
  } catch(ex) {
    cont.innerHTML = `<p style="color:var(--rojo);padding:20px">${esc(ex.message)}</p>`;
  }
}
/* ============================================================
   ESTUDIANTES
   ============================================================ */
let estudianteEditId  = null;
let estudiantesCache  = [];   // último listado traído del servidor (ya filtrado por búsqueda de texto)
let paginaActual      = 1;
 
function poblarFiltrosEstudiantes() {
  const selEsp = document.getElementById('filtro-especialidad');
  const selSec = document.getElementById('filtro-seccion');
  const selAnio= document.getElementById('filtro-anio');
 
  if (selEsp.dataset.poblado) return; // evita repoblar cada vez que se navega a la página
 
   actualizarBotonImprimirSeccion(); 
 
  ESPECIALIDADES.forEach(e => {
    const opt = document.createElement('option');
    opt.value = e.prefijo;
    opt.textContent = e.nombre;
    selEsp.appendChild(opt);
  });
  selEsp.dataset.poblado = '1';
 
  SECCIONES.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s;
    opt.textContent = s;
    selSec.appendChild(opt);
  });
 
  for (let a = ANIO_MIN; a <= ANIO_MAX; a++) {
    const opt = document.createElement('option');
    opt.value = String(a);
    opt.textContent = a + 'er/o Año';
    selAnio.appendChild(opt);
  }
}
 
// Interpreta el campo "codigo" (ej: "DS3A") en {especialidad, anio, seccion}
function parsearCodigo(codigo) {
  const m = /^([A-Za-z]+)(\d)([A-Za-z]+)$/.exec(String(codigo || '').trim());
  if (!m) return { especialidad: '', anio: '', seccion: '' };
  return { especialidad: m[1].toUpperCase(), anio: m[2], seccion: m[3].toUpperCase() };
}
 
function estudiantesFiltrados() {
  const esp  = document.getElementById('filtro-especialidad').value;
  const anio = document.getElementById('filtro-anio').value;
  const sec  = document.getElementById('filtro-seccion').value;
  if (!esp && !anio && !sec) return estudiantesCache;
 
  return estudiantesCache.filter(e => {
    const c = parsearCodigo(e.codigo);
    if (esp  && c.especialidad !== esp)  return false;
    if (anio && c.anio         !== anio) return false;
    if (sec  && c.seccion      !== sec)  return false;
    return true;
  });
}
 
async function cargarEstudiantes(q='') {
  poblarFiltrosEstudiantes();
  document.getElementById('tabla-estudiantes').innerHTML =
    `<tr><td colspan="8" class="tabla-vacia"><div class="spinner" style="margin:12px auto"></div></td></tr>`;
  try {
    estudiantesCache = await api('EstudiantesController.php', {action:'listar', q});
    paginaActual = 1;
    renderTablaEstudiantes();
  } catch(ex) { toast(ex.message, 'error'); }
}
 
function renderTablaEstudiantes() {
  const lista = estudiantesFiltrados();
  const tbody = document.getElementById('tabla-estudiantes');
 
  if (!lista.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="tabla-vacia">No se encontraron estudiantes.</td></tr>`;
    document.getElementById('paginacion-info').textContent = 'Mostrando 0 de 0 estudiantes';
    document.getElementById('paginacion-botones').innerHTML = '';
    return;
  }
 
  const totalPaginas = Math.max(1, Math.ceil(lista.length / ESTUDIANTES_POR_PAGINA));
  if (paginaActual > totalPaginas) paginaActual = totalPaginas;
  const inicio = (paginaActual - 1) * ESTUDIANTES_POR_PAGINA;
  const pagina = lista.slice(inicio, inicio + ESTUDIANTES_POR_PAGINA);
 
  tbody.innerHTML = pagina.map(e => `
<tr>
      <td>${esc(e.nie)}</td>
      <td>${esc(e.nombre)}</td>
      <td>${esc(e.apellido)}</td>
      <td><code style="font-size:.85rem;background:var(--verde-claro);padding:2px 8px;border-radius:6px">${esc(e.codigo)}</code></td>
      <td>${esc(e.telefono) || '—'}</td>
      <td>${esc(e.email) || '—'}</td>
      <td>
        <span class="chip-estado ${e.activo=='1' ? 'activo' : 'inactivo'}">
          <span class="punto"></span>${e.activo=='1' ? 'Activo' : 'Inactivo'}
        </span>
      </td>
      <td>
        <div class="celda-acciones">
          <button class="btn btn-sm btn-primary" title="Crear reporte" onclick="crearReporteEstudiante(${e.id})">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Registrar falta 
          </button>
          <button class="btn btn-sm btn-outline" title="Ver información" onclick="verEstudiante(${e.id})">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Ver información
          </button>
        </div>
      </td>
    </tr>`).join('');
 
  document.getElementById('paginacion-info').textContent =
    `Mostrando ${inicio + 1} a ${Math.min(inicio + ESTUDIANTES_POR_PAGINA, lista.length)} de ${lista.length} estudiantes`;
  renderPaginacion(totalPaginas);
}
 
function renderPaginacion(totalPaginas) {
  const cont = document.getElementById('paginacion-botones');
  if (totalPaginas <= 1) { cont.innerHTML = ''; return; }
 
  const flechaIzq = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>`;
  const flechaDer = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`;
 
  let html = `<button class="pg-btn" ${paginaActual===1?'disabled':''} onclick="irAPagina(${paginaActual-1})">${flechaIzq}</button>`;
 
  const paginas = new Set([1, totalPaginas, paginaActual, paginaActual-1, paginaActual+1]);
  let prev = 0;
  for (let p = 1; p <= totalPaginas; p++) {
    if (!paginas.has(p)) continue;
    if (p - prev > 1) html += `<span class="pg-ellipsis">…</span>`;
    html += `<button class="pg-btn ${p===paginaActual?'activo':''}" onclick="irAPagina(${p})">${p}</button>`;
    prev = p;
  }
 
  html += `<button class="pg-btn" ${paginaActual===totalPaginas?'disabled':''} onclick="irAPagina(${paginaActual+1})">${flechaDer}</button>`;
  cont.innerHTML = html;
}
 
function irAPagina(p) { paginaActual = p; renderTablaEstudiantes(); }
 
// Los 3 filtros desplegables y el botón "Limpiar filtros"
['filtro-especialidad', 'filtro-anio', 'filtro-seccion'].forEach(id => {
  document.getElementById(id).addEventListener('change', () => {
    paginaActual = 1;
    renderTablaEstudiantes();
    actualizarBotonImprimirSeccion();
  });
});
 
function codigoSeccionActual() {
  const esp  = document.getElementById('filtro-especialidad').value;
  const anio = document.getElementById('filtro-anio').value;
  const sec  = document.getElementById('filtro-seccion').value;
  if (!esp || !anio || !sec) return null;
  return `${esp}${anio}${sec}`; // Ej: "ITSI" + "3" + "A" = "ITSI3A"
}
 
function actualizarBotonImprimirSeccion() {
  const btn = document.getElementById('btn-imprimir-seccion');
  const codigo = codigoSeccionActual();
  btn.disabled = !codigo;
  btn.title = codigo
    ? `Imprimir reporte de la sección ${codigo}`
    : 'Selecciona Especialidad, Año y Sección para habilitar esta opción';
}
 
document.getElementById('btn-imprimir-seccion').addEventListener('click', () => {
  const codigo = codigoSeccionActual();
  if (!codigo) return;
  window.open(`${BASE_API}/ReportesController.php?action=pdf_seccion&codigo=${encodeURIComponent(codigo)}`, '_blank');
});
document.getElementById('btn-limpiar-filtros').addEventListener('click', () => {
  document.getElementById('buscar-estudiante').value = '';
  document.getElementById('filtro-especialidad').value = '';
  document.getElementById('filtro-anio').value = '';
  document.getElementById('filtro-seccion').value = '';
  actualizarBotonImprimirSeccion(); 
  cargarEstudiantes();
});
 
let debounceBusqueda;
document.getElementById('buscar-estudiante').addEventListener('input', e => {
  clearTimeout(debounceBusqueda);
  debounceBusqueda = setTimeout(() => cargarEstudiantes(e.target.value), 300);
});
 
function abrirModalEstudiante(id=null) {
  estudianteEditId = id;
  document.getElementById('form-estudiante').reset();
  document.getElementById('modal-est-titulo').textContent =
    id ? 'Editar Estudiante' : 'Registrar Estudiante';
  document.getElementById('modal-estudiante').classList.add('abierto');
}
 
async function editarEstudiante(id) {
  abrirModalEstudiante(id);
  try {
    const e = await api('EstudiantesController.php', {action:'ver', id});
    document.getElementById('est-nie').value      = e.nie;
    document.getElementById('est-nombre').value   = e.nombre;
    document.getElementById('est-apellido').value = e.apellido;
    document.getElementById('est-codigo').value   = e.codigo;
    document.getElementById('est-fecha').value    = e.fecha_nac || '';
    document.getElementById('est-telefono').value = e.telefono || '';
    document.getElementById('est-email').value    = e.email || '';
  } catch(ex) { toast(ex.message, 'error'); }
}
 
async function verEstudiante(id) {
  try {
    const e = await api('EstudiantesController.php', {action:'ver', id});
    const c = parsearCodigo(e.codigo);
    const especialidad = ESPECIALIDADES.find(x => x.prefijo === c.especialidad);
    document.getElementById('detalle-estudiante-contenido').innerHTML = `
      <div class="detalle-item"><div class="etiqueta">NIE</div><div class="valor">${esc(e.nie)}</div></div>
      <div class="detalle-item"><div class="etiqueta">Código</div><div class="valor">${esc(e.codigo)}</div></div>
      <div class="detalle-item"><div class="etiqueta">Nombre completo</div><div class="valor">${esc(e.nombre)} ${esc(e.apellido)}</div></div>
      <div class="detalle-item"><div class="etiqueta">Estado</div><div class="valor">${e.activo=='1'?'Activo':'Inactivo'}</div></div>
      <div class="detalle-item"><div class="etiqueta">Especialidad</div><div class="valor">${especialidad ? esc(especialidad.nombre) : '—'}</div></div>
      <div class="detalle-item"><div class="etiqueta">Año / Sección</div><div class="valor">${c.anio ? c.anio+'° "'+c.seccion+'"' : '—'}</div></div>
      <div class="detalle-item"><div class="etiqueta">Teléfono</div><div class="valor">${esc(e.telefono) || '—'}</div></div>
      <div class="detalle-item"><div class="etiqueta">Email</div><div class="valor">${esc(e.email) || '—'}</div></div>
      <div class="detalle-item"><div class="etiqueta">Fecha de nacimiento</div><div class="valor">${esc(e.fecha_nac) || '—'}</div></div>
    `;
 
    // Récord de faltas del estudiante
    const contenedorFaltas = document.getElementById('detalle-estudiante-faltas');
    contenedorFaltas.innerHTML = `<div class="loading"><div class="spinner"></div>Cargando faltas...</div>`;
    try {
      const faltas = await api('FaltasController.php', {action:'por_estudiante', estudiante_id:id});
      if (!faltas.length) {
        contenedorFaltas.innerHTML =
          `<div class="empty-state"><div class="ei">—</div><p>Este estudiante no tiene faltas registradas.</p></div>`;
      } else {
        contenedorFaltas.innerHTML = faltas.map(f => {
          const tieneSancion = !!f.sancion_id;
          const nombreEst    = JSON.stringify(e.nombre + ' ' + e.apellido).replace(/"/g, "'");
          // Sin sanción -> abre "Aplicar sanción". Con sanción (cualquier estado) -> va a Sanciones.
          const accionClic = !tieneSancion
            ? `seleccionarFaltaDesdeDetalle(${f.id}, ${nombreEst}, false)`
            : `seleccionarFaltaDesdeDetalle(${f.id}, ${nombreEst}, true)`;

          return `
          <div class="historial-item ${f.gravedad}" style="cursor:pointer" onclick="${accionClic}">
            <div>
              <div class="historial-titulo">${esc(f.tipo_falta)}
                <span class="badge badge-${f.gravedad}">${f.gravedad}</span>
              </div>
              ${f.descripcion
                ? `<div class="historial-sub" style="margin-top:4px;color:var(--texto-dark)">${esc(f.descripcion)}</div>`
                : ''}
              <div class="historial-sub" style="margin-top:4px">Registrado por: ${esc(f.registrado_por)}</div>
              ${tieneSancion
                ? `<div class="historial-sub" style="margin-top:4px;font-style:italic">
                     Sanción asignada: ${esc(etiquetaSancion(f.sancion_tipo))} (${esc(f.sancion_estado)})
                   </div>`
                : `<div class="historial-sub" style="margin-top:4px;color:var(--primario);font-style:italic">
                     Sin sanción asignada — clic para aplicar una
                   </div>`}
            </div>
            <div class="historial-fecha">${esc(f.fecha)}</div>
          </div>`;
        }).join('');
      }
    } catch(exFaltas) {
      contenedorFaltas.innerHTML = `<div class="empty-state"><p>No se pudo cargar el récord de faltas.</p></div>`;
    }
 
    document.getElementById('btn-dar-baja-modal').onclick = () => eliminarEstudiante(e.id, true);
    document.getElementById('btn-dar-baja-modal').style.display = e.activo=='1' ? '' : 'none';
    document.getElementById('btn-editar-modal').onclick = () => {
      cerrarModal('modal-ver-estudiante');
      editarEstudiante(e.id);
    };
    document.getElementById('modal-ver-estudiante').classList.add('abierto');
  } catch(ex) { toast(ex.message, 'error'); }
}
 
async function eliminarEstudiante(id, desdeModal=false) {
  if (!confirm('Esta acción dará de baja al estudiante del sistema. ¿Desea continuar?')) return;
  try {
    await api('EstudiantesController.php', {action:'eliminar', id}, null, 'DELETE');
    toast('Estudiante dado de baja correctamente.');
    if (desdeModal) cerrarModal('modal-ver-estudiante');
    cargarEstudiantes(document.getElementById('buscar-estudiante').value);
  } catch(ex) { toast(ex.message, 'error'); }
}
 
document.getElementById('form-estudiante').addEventListener('submit', async e => {
  e.preventDefault();
  const nie = document.getElementById('est-nie').value.trim();
  if (!/^\d{7}$/.test(nie)) {
    toast('El NIE debe contener exactamente 7 dígitos numéricos.', 'error');
    return;
  }
  const body = {
    nie,
    nombre:    document.getElementById('est-nombre').value,
    apellido:  document.getElementById('est-apellido').value,
    codigo:    document.getElementById('est-codigo').value,
    fecha_nac: document.getElementById('est-fecha').value,
    telefono:  document.getElementById('est-telefono').value,
    email:     document.getElementById('est-email').value,
  };
  try {
    if (estudianteEditId) {
      await api('EstudiantesController.php', {action:'actualizar', id:estudianteEditId}, body, 'PUT');
      toast('Estudiante actualizado correctamente.');
    } else {
      await api('EstudiantesController.php', {action:'crear'}, body, 'POST');
      toast('Estudiante registrado correctamente.');
    }
    cerrarModal('modal-estudiante');
    cargarEstudiantes(document.getElementById('buscar-estudiante').value);
  } catch(ex) { toast(ex.message, 'error'); }
});

/* ============================================================
   FALTAS
   ============================================================ */
async function cargarFaltas() {
  document.getElementById('tabla-faltas').innerHTML =
    `<tr><td colspan="6" class="tabla-vacia"><div class="spinner" style="margin:12px auto"></div></td></tr>`;
  try {
    const lista = await api('FaltasController.php', {action:'listar'});
    if (!lista.length) {
      document.getElementById('tabla-faltas').innerHTML =
        `<tr><td colspan="6" class="tabla-vacia">No hay faltas registradas.</td></tr>`;
      return;
    }
    document.getElementById('tabla-faltas').innerHTML = lista.map(f => `
      <tr>
        <td>${esc(f.nie)}</td>
        <td>${esc(f.estudiante)}</td>
        <td>${esc(f.tipo_falta)}</td>
        <td><span class="badge badge-${f.gravedad}">${f.gravedad}</span></td>
        <td>${esc(f.fecha)}</td>
        <td>
          <button class="btn btn-warning btn-sm"
            onclick="abrirSancion(${f.id}, ${JSON.stringify(f.estudiante).replace(/"/g,"'")})">
            Sancionar
          </button>
          <button class="btn btn-danger btn-sm" onclick="eliminarFalta(${f.id})">Eliminar</button>
        </td>
      </tr>`).join('');
  } catch(ex) { toast(ex.message, 'error'); }
}
 
/* ============================================================
   AUTOCOMPLETE DE ESTUDIANTES (buscar por NIE o nombre)
   ============================================================ */
const autocompleteEstData = {}; // { hiddenId: [lista de estudiantes] }
 
function initAutocompleteEstudiante(inputId, hiddenId, listaId) {
  const input  = document.getElementById(inputId);
  const hidden = document.getElementById(hiddenId);
  const panel  = document.getElementById(listaId);
 
  function render(filtro) {
    const lista = autocompleteEstData[hiddenId] || [];
    const q = filtro.trim().toLowerCase();
    const resultados = !q ? lista : lista.filter(e =>
      (e.nie || '').toLowerCase().includes(q) ||
      `${e.nombre || ''} ${e.apellido || ''}`.toLowerCase().includes(q)
    );
    panel.innerHTML = !resultados.length
      ? `<div class="autocomplete-vacio">Sin resultados</div>`
      : resultados.slice(0, 30).map(e => `
          <div class="autocomplete-item" data-id="${e.id}"
               data-texto="${esc(e.nombre)} ${esc(e.apellido)} — ${esc(e.nie)}">
            <span class="autocomplete-nombre">${esc(e.nombre)} ${esc(e.apellido)}</span>
            <span class="autocomplete-nie">NIE ${esc(e.nie)}</span>
          </div>`).join('');
    panel.classList.add('abierto');
  }
 
  if (!input.dataset.autocompleteListo) {
    input.dataset.autocompleteListo = '1';
    input.addEventListener('focus', () => render(input.value));
    input.addEventListener('input', () => { hidden.value = ''; render(input.value); });
    panel.addEventListener('click', e => {
      const item = e.target.closest('.autocomplete-item');
      if (!item) return;
      hidden.value = item.dataset.id;
      input.value  = item.dataset.texto;
      panel.classList.remove('abierto');
    });
    document.addEventListener('click', e => {
      if (!input.contains(e.target) && !panel.contains(e.target)) {
        panel.classList.remove('abierto');
      }
    });
  }
}
 
async function cargarAutocompleteEstudiantes(inputId, hiddenId, listaId) {
  const input = document.getElementById(inputId);
  input.value = '';
  input.placeholder = 'Cargando...';
  input.disabled = true;
  try {
    autocompleteEstData[hiddenId] = await api('EstudiantesController.php', {action:'listar'});
    input.disabled = false;
    input.placeholder = 'Buscar por NIE o nombre...';
    initAutocompleteEstudiante(inputId, hiddenId, listaId);
  } catch(ex) {
    input.disabled = false;
    toast(ex.message, 'error');
  }
}
 
async function cargarSelectTipos(selectId) {
  const sel = document.getElementById(selectId);
  sel.innerHTML = '<option value="">Cargando...</option>';
  const lista = await api('FaltasController.php', {action:'tipos'});
  sel.innerHTML = '<option value="">-- Seleccione tipo --</option>' +
    lista.map(t => `<option value="${t.id}">[${t.gravedad}] ${esc(t.nombre)}</option>`).join('');
}
 
async function abrirModalFalta() {
  document.getElementById('form-falta').reset();
  document.getElementById('modal-falta').classList.add('abierto');
  await Promise.all([
    cargarAutocompleteEstudiantes('falta-estudiante-buscar', 'falta-estudiante', 'falta-estudiante-lista'),
    cargarSelectTipos('falta-tipo'),
  ]);
  document.getElementById('falta-fecha').value = new Date().toISOString().slice(0,10);
}
 
document.getElementById('form-falta').addEventListener('submit', async e => {
  e.preventDefault();
  const estudianteId = document.getElementById('falta-estudiante').value;
  if (!estudianteId) { toast('Selecciona un estudiante de la lista de sugerencias.', 'error'); return; }
  const body = {
    estudiante_id: estudianteId,
    tipo_falta_id: document.getElementById('falta-tipo').value,
    fecha:         document.getElementById('falta-fecha').value,
    descripcion:   document.getElementById('falta-desc').value,
  };
  try {
    await api('FaltasController.php', {action:'crear'}, body, 'POST');
    toast('Falta registrada correctamente.');
    cerrarModal('modal-falta');
    cargarFaltas();
  } catch(ex) { toast(ex.message, 'error'); }
});
 
async function eliminarFalta(id) {
  if (!confirm('¿Está seguro que desea eliminar esta falta?')) return;
  try {
    await api('FaltasController.php', {action:'eliminar', id}, null, 'DELETE');
    toast('Falta eliminada.');
    cargarFaltas();
  } catch(ex) { toast(ex.message, 'error'); }
}

/* ============================================================
   SANCIONES
   ============================================================ */
let sancionFaltaId = null, sancionEstId = null;
 
async function cargarSanciones() {
  document.getElementById('tabla-sanciones').innerHTML =
    `<tr><td colspan="6" class="tabla-vacia"><div class="spinner" style="margin:12px auto"></div></td></tr>`;
  try {
    const lista = await api('SancionesController.php', {action:'listar'});
    if (!lista.length) {
      document.getElementById('tabla-sanciones').innerHTML =
        `<tr><td colspan="6" class="tabla-vacia">No hay sanciones registradas.</td></tr>`;
      return;
    }
  document.getElementById('tabla-sanciones').innerHTML = lista.map(s => `
      <tr>
        <td>${esc(s.nie)}</td>
        <td>${esc(s.estudiante)}</td>
        <td>${etiquetaSancion(s.tipo_sancion)}</td>
        <td>${esc(s.fecha_inicio)}${s.fecha_fin ? ' — ' + esc(s.fecha_fin) : ''}</td>
        <td><span class="badge badge-${s.estado}">${s.estado}</span></td>
        <td>
          <div class="celda-acciones">
            <button class="btn btn-sm btn-outline" title="Imprimir PDF"
              onclick="imprimirReporteEstudiante(${s.estudiante_id})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              Imprimir PDF
            </button>
            ${s.estado==='activa'
              ? `<button class="btn btn-success btn-sm"
                  onclick="cambiarEstado(${s.id},'cumplida')">Marcar cumplida</button>`
              : ''}
          </div>
        </td>
      </tr>`).join('');
  } catch(ex) { toast(ex.message, 'error'); }
}
 
function etiquetaSancion(tipo) {
  return {
    amonestacion_verbal: 'Amonestación verbal',
    horas_comunitarias:  'Horas de servicio comunitario',
    citacion_padre:       'Citación de padre / tutor',
    suspension:           'Suspensión parcial',
    retiro_definitivo:    'Retiro definitivo',
    otro:                 'Otra medida',
  }[tipo] || tipo;
}
 
/* Catálogo de sanciones aplicables según la gravedad de la falta
   (moderada = GRAVE del manual, grave = MUY GRAVE del manual) */
const SANCIONES_POR_GRAVEDAD = {
  moderada: [
    {value:'amonestacion_verbal', label:'Amonestación verbal'},
    {value:'horas_comunitarias',  label:'Horas de servicio comunitario'},
    {value:'citacion_padre',      label:'Citación de padre/tutor'},
    {value:'otro',                label:'Otra medida'},
  ],
  grave: [
    {value:'suspension',        label:'Suspensión parcial'},
    {value:'retiro_definitivo', label:'Retiro definitivo'},
    {value:'otro',              label:'Otra medida'},
  ],
};
 
function cargarSelectSancion(gravedad) {
  const sel = document.getElementById('sancion-tipo');
  const opciones = SANCIONES_POR_GRAVEDAD[gravedad] || [
    ...SANCIONES_POR_GRAVEDAD.moderada,
    ...SANCIONES_POR_GRAVEDAD.grave,
  ];
  sel.innerHTML = '<option value="">-- Selecciona --</option>' +
    opciones.map(o => `<option value="${o.value}">${esc(o.label)}</option>`).join('');
}
 
function abrirSancion(faltaId, nombre) {
  sancionFaltaId = faltaId;
  document.getElementById('sancion-titulo-est').textContent = nombre;
  document.getElementById('form-sancion').reset();
  document.getElementById('sancion-fecha-ini').value = new Date().toISOString().slice(0,10);
  cargarSelectSancion(null); // muestra todas mientras carga la gravedad real
  api('FaltasController.php', {action:'listar'}).then(lista => {
    const f = lista.find(f => f.id == faltaId);
    if (f) {
      cargarSelectSancion(f.gravedad);
      api('EstudiantesController.php', {action:'listar', q: f.nie}).then(es => {
        if (es[0]) sancionEstId = es[0].id;
      });
    }
  });
  navegar('sanciones');
  document.getElementById('modal-sancion').classList.add('abierto');
}

// Se llama al hacer clic en una falta dentro del modal "Detalle del Estudiante".
// - Sin sanción asignada: cierra el modal y abre "Aplicar sanción" para esa falta.
// - Con sanción ya asignada (cualquier estado): cierra el modal y lleva a la pantalla de Sanciones.
function seleccionarFaltaDesdeDetalle(faltaId, nombreEstudiante, tieneSancion) {
  cerrarModal('modal-ver-estudiante');
  if (tieneSancion) {
    navegar('sanciones');
  } else {
    abrirSancion(faltaId, nombreEstudiante);
  }
}
 
document.getElementById('form-sancion').addEventListener('submit', async e => {
  e.preventDefault();
  const body = {
    falta_id:      sancionFaltaId,
    estudiante_id: sancionEstId,
    tipo_sancion:  document.getElementById('sancion-tipo').value,
    descripcion:   document.getElementById('sancion-desc').value,
    fecha_inicio:  document.getElementById('sancion-fecha-ini').value,
    fecha_fin:     document.getElementById('sancion-fecha-fin').value,
  };
  try {
    await api('SancionesController.php', {action:'crear'}, body, 'POST');
    toast('Sanción aplicada correctamente.');
    cerrarModal('modal-sancion');
    cargarSanciones();
  } catch(ex) { toast(ex.message, 'error'); }
});
 
async function cambiarEstado(id, estado) {
  try {
    await api('SancionesController.php', {action:'actualizar_estado', id}, {estado}, 'PUT');
    toast('Estado de sanción actualizado.');
    cargarSanciones();
  } catch(ex) { toast(ex.message, 'error'); }
}
 
/* ============================================================
   HISTORIAL
   ============================================================ */
async function cargarHistorial() {
  const contenedor = document.getElementById('lista-historial');
  contenedor.innerHTML = `<div class="loading"><div class="spinner"></div>Cargando historial...</div>`;
  try {
    const lista = await api('FaltasController.php', {action:'listar'});
    if (!lista.length) {
      contenedor.innerHTML =
        `<div class="empty-state"><div class="ei">—</div><p>No hay registros en el historial.</p></div>`;
      return;
    }
    contenedor.innerHTML = lista.map(f => `
      <div class="historial-item ${f.gravedad}">
        <div>
          <div class="historial-titulo">
            ${esc(f.estudiante)}
            <small style="color:var(--texto-gris);font-weight:400"> — NIE: ${esc(f.nie)}</small>
          </div>
          <div class="historial-sub">
            ${esc(f.tipo_falta)} &nbsp;
            <span class="badge badge-${f.gravedad}">${f.gravedad}</span>
          </div>
          ${f.descripcion
            ? `<div class="historial-sub" style="margin-top:4px;color:var(--texto-dark)">${esc(f.descripcion)}</div>`
            : ''}
          <div class="historial-sub" style="margin-top:4px">
            Registrado por: ${esc(f.registrado_por)}
          </div>
        </div>
        <div class="historial-fecha">${esc(f.fecha)}</div>
      </div>`).join('');
  } catch(ex) { toast(ex.message, 'error'); }
}
 
function imprimirReporteEstudiante(id) {
  window.open(`${BASE_API}/ReportesController.php?action=pdf_estudiante&id=${id}`, '_blank');
}
/* ============================================================
   REPORTES - ENVIO POR CORREO
   ============================================================ */
async function crearReporteEstudiante(id) {
  try {
    const e = await api('EstudiantesController.php', {action:'ver', id});
    document.getElementById('form-falta').reset();
    document.getElementById('modal-falta').classList.add('abierto');
    await cargarSelectTipos('falta-tipo');
    document.getElementById('falta-fecha').value = new Date().toISOString().slice(0,10);
 
    // Pre-seleccionamos al estudiante de esta fila y bloqueamos el campo
    // para que la falta quede registrada exactamente a ese estudiante.
    document.getElementById('falta-estudiante').value = e.id;
    const inputBuscar = document.getElementById('falta-estudiante-buscar');
    inputBuscar.value = `${e.nombre} ${e.apellido} (NIE ${e.nie})`;
    inputBuscar.disabled = true;
  } catch(ex) { toast(ex.message, 'error'); }
}
 
/* ============================================================
   MODAL HELPERS
   ============================================================ */
function cerrarModal(id) { document.getElementById(id).classList.remove('abierto'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('abierto'); });
});
 
/* ============================================================
   MANEJADORES POR PAGINA
   ============================================================ */
const manejadoresPagina = {
  dashboard:   cargarDashboard,
  estudiantes: () => cargarEstudiantes(),
  faltas:      cargarFaltas,
  sanciones:   cargarSanciones,
  historial:   cargarHistorial,
  reportes:    () => {},
};
 
/* ============================================================
   INICIO
   ============================================================ */
checkSesion();