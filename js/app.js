document.addEventListener("DOMContentLoaded", () => {
    
    // 1. CAPTURAR ELEMENTOS DEL DOM
    const inputBarbero = document.getElementById("input_barbero");
    const inputHora = document.getElementById("input_hora");
    const inputFecha = document.querySelector("input[type='date']");
    const contenedorBarberos = document.getElementById("contenedor-barberos-dinamicos");

    // VARIABLE GLOBAL TEMPORAL PARA GUARDAR EL TELÉFONO DEL BARBERO SELECCIONADO
    let telefonoBarberoActivo = "573000000000"; 

    // CLASES DE ESTILOS TAILWIND PARA BARBEROS
    const claseActivo = ["bg-amber-500", "text-slate-950", "border-amber-500", "shadow-lg", "shadow-amber-500/10"];
    const claseInactivo = ["bg-[#111827]", "text-slate-300", "border-slate-700"];

    // 0. BLOQUEAR FECHAS PASADAS EN EL CALENDARIO AUTOMÁTICAMENTE
    const hoy = new Date();
    const año = hoy.getFullYear();
    const mes = String(hoy.getMonth() + 1).padStart(2, '0');
    const dia = String(hoy.getDate()).padStart(2, '0');
    const fechaMinima = `${año}-${mes}-${dia}`;
    
    if (inputFecha) {
        inputFecha.min = fechaMinima;
        inputFecha.value = fechaMinima; 
    }

    // CARGAR BARBEROS DESDE LA BASE DE DATOS
    function cargarBarberosDesdeBD() {
        if (!contenedorBarberos) return;

        fetch('backend/obtener_barberos.php')
            .then(response => response.json())
            .then(barberos => {
                contenedorBarberos.innerHTML = ""; 

                if (barberos.length === 0) {
                    contenedorBarberos.innerHTML = `<p class="text-xs text-slate-500 italic col-span-2 text-center py-4">No hay barberos registrados.</p>`;
                    return;
                }

                barberos.forEach(barbero => {
                    const boton = document.createElement("button");
                    boton.type = "button";
                    boton.setAttribute("data-id", barbero.id);
                    boton.setAttribute("data-telefono", barbero.telefono); // Guardamos el teléfono en el botón HTML
                    boton.className = "btn-barbero bg-[#111827] text-slate-300 border border-slate-700 p-4 rounded-xl flex flex-col items-center justify-center text-center transition";
                    
                    boton.innerHTML = `
                        <span class="font-bold text-base">${barbero.nombre}</span>
                        <span class="text-xs text-slate-500">Barbero Profesional</span>
                    `;

                    boton.addEventListener("click", () => {
                        document.querySelectorAll(".btn-barbero").forEach(b => {
                            b.classList.remove(...claseActivo);
                            b.classList.add(...claseInactivo);
                        });

                        boton.classList.remove(...claseInactivo);
                        boton.classList.add(...claseActivo);

                        inputBarbero.value = barbero.id;
                        
                        // CAPTURAMOS EL TELÉFONO REAL DE LA BASE DE DATOS
                        telefonoBarberoActivo = barbero.telefono ? barbero.telefono : "573000000000";
                        
                        actualizarHorasDisponibles();
                    });

                    contenedorBarberos.appendChild(boton);
                });
            })
            .catch(error => {
                console.error("Error al cargar los barberos:", error);
                contenedorBarberos.innerHTML = `<p class="text-xs text-rose-400 col-span-2 text-center py-4">Error al conectar con los barberos.</p>`;
            });
    }

    cargarBarberosDesdeBD();

    // CONSULTAR DISPONIBILIDAD MEDIANTE AJAX
    function actualizarHorasDisponibles() {
        const barberoId = inputBarbero.value;
        const fechaSeleccionada = inputFecha.value;
        const contenedor = document.getElementById("contenedor-horas-dinamicas");

        if (!contenedor) return;
        if (!barberoId || !fechaSeleccionada) return;

        inputHora.value = "";
        contenedor.innerHTML = `<p class="text-xs text-amber-500/70 italic col-span-3 text-center py-2">Consultando agenda...</p>`;

        fetch(`backend/obtener_horas_ocupadas.php?barbero_id=${barberoId}&fecha=${fechaSeleccionada}`)
            .then(response => response.json())
            .then(data => {
                contenedor.innerHTML = ""; 

                // CASO A: El barbero no ha publicado ningún horario para este día
                if (!data.habilitados || data.habilitados.length === 0) {
                    const fechaBonita = new Date(fechaSeleccionada + 'T00:00:00').toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });
                    const mensajeWS = encodeURIComponent(`¡Hola! Estaba revisando la página web y no veo horarios disponibles para el día ${fechaBonita}. ¿Me podrías confirmar si vas a trabajar o me habilitas un cupo, por favor? 💈✂️`);
                    
                    // PASAMOS EL TELÉFONO DINÁMICO AQUÍ
                    const linkWhatsApp = `https://wa.me/${telefonoBarberoActivo}?text=${mensajeWS}`; 

                    contenedor.innerHTML = `
                        <div class="col-span-3 text-center space-y-3 p-3 bg-amber-500/5 rounded-xl border border-amber-500/20">
                            <p class="text-xs text-slate-400 leading-relaxed">Este barbero aún no ha publicado sus turnos para esta fecha.</p>
                            <a href="${linkWhatsApp}" target="_blank" class="inline-flex items-center space-x-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs transition shadow-md shadow-emerald-600/10">
                                <span>💬</span> <span>Preguntar por WhatsApp</span>
                            </a>
                        </div>
                    `;
                    return;
                }

                // CASO B: Sí hay horarios creados por el barbero
                const ahora = new Date();
                const horaActualStr = String(ahora.getHours()).padStart(2, '0') + ":" + String(ahora.getMinutes()).padStart(2, '0') + ":00";
                const esHoy = (fechaSeleccionada === fechaMinima);

                data.habilitados.forEach(hora => {
                    const [h, m] = hora.split(':');
                    const horaInt = parseInt(h);
                    const ampm = horaInt >= 12 ? 'PM' : 'AM';
                    const hora12 = horaInt % 12 === 0 ? 12 : horaInt % 12;
                    const horaTexto = `${hora12}:${m} ${ampm}`;

                    const boton = document.createElement("button");
                    boton.type = "button";
                    boton.setAttribute("data-hora", hora);
                    boton.innerText = horaTexto;

                    const yaPaso = esHoy && (hora < horaActualStr);

                    if ((data.ocupados && data.ocupados.includes(hora)) || yaPaso) {
                        boton.disabled = true;
                        boton.className = "bg-[#111827]/30 border border-slate-800/80 py-2 rounded-lg text-xs font-medium text-slate-600 cursor-not-allowed line-through opacity-40";
                    } else {
                        boton.className = "btn-hora-dinamica bg-[#111827] border border-slate-700 hover:border-amber-500 py-2 rounded-lg text-xs font-medium text-slate-200 transition";
                        
                        boton.addEventListener("click", () => {
                            document.querySelectorAll(".btn-hora-dinamica").forEach(b => {
                                b.classList.remove("bg-amber-500", "text-slate-950", "font-bold", "shadow-lg", "shadow-amber-500/20");
                                b.classList.add("bg-[#111827]", "text-slate-200");
                            });
                            boton.classList.remove("bg-[#111827]", "text-slate-200");
                            boton.classList.add("bg-amber-500", "text-slate-950", "font-bold", "shadow-lg", "shadow-amber-500/20");
                            inputHora.value = hora; 
                        });
                    }
                    contenedor.appendChild(boton);
                });
            })
            .catch(error => {
                console.error("Error al renderizar la agenda:", error);
                contenedor.innerHTML = `<p class="text-xs text-rose-400 col-span-3 text-center py-2">Error al cargar horarios.</p>`;
            });
    }

    if (inputFecha) {
        inputFecha.addEventListener("change", () => {
            actualizarHorasDisponibles();
        });
    }

    const parametrosUrl = new URLSearchParams(window.location.search);
    const estado = parametrosUrl.get("estado");

    if (estado === "exito") {
        const modal = document.getElementById("modal-exito");
        if (modal) modal.classList.remove("hidden");
        window.history.replaceState({}, document.title, window.location.pathname);
    } 
    if (estado === "ocupado") {
        alert("❌ Lo sentimos, este horario ya fue reservado por otro cliente.");
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    const formulario = document.querySelector("form");
    if (formulario) {
        formulario.addEventListener("submit", (evento) => {
            const barberoSeleccionado = inputBarbero.value;
            const horaSeleccionada = inputHora.value;

            if (!barberoSeleccionado) {
                evento.preventDefault();
                alert("⚠️ Por favor, selecciona un barbero antes de continuar.");
                return;
            }
            if (!horaSeleccionada) {
                evento.preventDefault();
                alert("⚠️ Por favor, selecciona un horario disponible para tu cita.");
                return;
            }
        });
    }

    window.cerrarModal = function() {
        const modal = document.getElementById("modal-exito");
        if (modal) modal.classList.add("hidden");
    }
});