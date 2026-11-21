/* Sophie Oefenportaal – chat (zonder AI, gratis) en contactformulier. */
(function () {
	'use strict';
	var C = window.LS_CHAT || {};
	var TEL = C.telefoon || '0472 22 01 62';
	var TERMIJN = C.termijn || '2 werkdagen';

	function esc(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function post(data) {
		return fetch(C.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-WP-Nonce': C.nonce || '' },
			body: JSON.stringify(data)
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { throw new Error(j && j.message ? j.message : 'Versturen lukte niet.'); }
				return j;
			});
		});
	}

	/* ---------- Contactformulier ---------- */
	document.querySelectorAll('form[data-ls-contact]').forEach(function (f) {
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = f.querySelector('.ls-op-error'), ok = f.querySelector('.ls-op-ok'), btn = f.querySelector('button[type=submit]');
			var d = {}; new FormData(f).forEach(function (v, k) { d[k] = v; });
			d.bron = 'contactformulier';
			err.hidden = true;
			if ((d.naam || '').trim().length < 2) { err.textContent = 'Vul je naam in.'; err.hidden = false; return; }
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.mail || '') && (d.tel || '').replace(/\D/g, '').length < 9) { err.textContent = 'Vul een telefoonnummer of e-mailadres in.'; err.hidden = false; return; }
			if (!d.toestemming) { err.textContent = 'Vink aan dat je gegevens naar de praktijk mogen.'; err.hidden = false; return; }
			btn.disabled = true; btn.textContent = 'Bezig…';
			post(d).then(function () {
				f.querySelectorAll('label, .ls-op-row, button').forEach(function (n) { n.hidden = true; });
				ok.textContent = 'Bedankt, ' + d.naam.split(' ')[0] + '! Je aanvraag is verstuurd. Sophie neemt binnen ' + TERMIJN + ' contact op.';
				ok.hidden = false;
			}).catch(function (x) {
				err.textContent = x.message + ' Je kan ook bellen naar ' + TEL + '.'; err.hidden = false;
				btn.disabled = false; btn.textContent = 'Aanvraag versturen';
			});
		});
	});

	if (!C.chat) { return; }

	/* ---------- Chat ---------- */
	var root = document.createElement('div');
	root.className = 'ls-chat';
	root.innerHTML =
		'<button type="button" class="ls-chat-fab" aria-label="Open de chat" aria-expanded="false" aria-controls="lsChatPanel"><span class="c"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v10H10l-5 4v-4H4z"/></svg></span><span class="t">Kan ik je helpen?</span></button>' +
		'<section class="ls-chat-panel" id="lsChatPanel" aria-label="Chat met Logopedie Sophie" hidden>' +
		'<div class="ls-chat-head"><div class="ls-chat-title"><strong>Logopedie Sophie</strong><span>Vragen en afspraak aanvragen</span></div>' +
		'<button type="button" class="ls-chat-x" aria-label="Chat sluiten"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>' +
		'<div class="ls-chat-log" aria-live="polite"></div><div class="ls-chat-sugg"></div>' +
		'<form class="ls-chat-form"><label class="screen-reader-text" for="lsChatInput">Je vraag</label><textarea id="lsChatInput" rows="1" placeholder="Typ hier…" maxlength="800"></textarea>' +
		'<button type="submit" aria-label="Versturen"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></form>' +
		'<p class="ls-chat-foot">Je gegevens gaan enkel naar Sophie en worden niet voor andere doeleinden gebruikt. Liever bellen? ' + esc(TEL) + '.</p></section>';
	document.body.appendChild(root);

	var fab = root.querySelector('.ls-chat-fab'), panel = root.querySelector('.ls-chat-panel'), log = root.querySelector('.ls-chat-log'),
		sugg = root.querySelector('.ls-chat-sugg'), form = root.querySelector('.ls-chat-form'), input = root.querySelector('textarea');
	var chat = { step: null, data: {} };

	var MENU = ['Afspraak aanvragen', 'Wordt het terugbetaald?', 'Hoe verloopt een traject?', 'Wachttijd', 'Oefenportaal', 'Webshop', 'Iets anders vragen'];
	var HULP = ['Lezen of spelling (dyslexie)', 'Rekenen (dyscalculie)', 'Taal of spraak', 'Afasie', 'Mondgewoonten (OMFT)', 'Blind leren typen', 'Weet ik nog niet'];
	var ANSWERS = {
		terug: 'Voor de meeste hulpvragen komt het RIZIV tussen via je ziekenfonds. Je hebt daarvoor een voorschrift van de huisarts nodig. Of jouw situatie in aanmerking komt, bekijkt Sophie na het onderzoek. De tarieven vind je op de pagina Werkwijze en tarieven.',
		traject: 'Zo gaat het: 1. je vraagt een voorschrift bij de huisarts, 2. Sophie doet een onderzoek en schrijft een bilan, 3. de huisarts of specialist keurt het goed, 4. de behandeling start, met oefeningen voor thuis in het oefenportaal.',
		wacht: 'Sophie houdt de wachttijd zo kort mogelijk. De actuele wachttijd hangt af van de hulpvraag en je beschikbaarheid. Laat je gegevens achter, dan laat Sophie je weten wanneer je kan starten.',
		portaal: 'Na de start van de behandeling krijg je een uitnodigingscode om een account aan te maken. Sophie zet daar oefeningen klaar voor de dagen zonder therapie, en in de Oefenhoek kan je vrij oefenen van kleuter tot 6e leerjaar.',
		shop: 'In de webshop vind je logopediemateriaal, oefenbundels, educatief speelgoed, schrijfwaren en Sophie\'s eigen AVI-leesboekjes, die op bestelling gedrukt en thuis geleverd worden. Afhalen in de praktijk in Runkst kan ook.',
		adres: 'De praktijk is aan huis: Paters Adonsstraat 25, 3500 Hasselt (Runkst). Telefoon ' + TEL + ', e-mail info@logopediesophie.be.',
		prijs: 'De tarieven staan op de pagina Werkwijze en tarieven. Een deel wordt meestal terugbetaald via het ziekenfonds, met een voorschrift van de huisarts.',
		volw: 'Ja, Sophie werkt ook met volwassenen, onder meer bij afasie na een beroerte of hersenletsel.'
	};
	var GREET = 'Hallo! Ik ben de chat van Logopedie Sophie. Ik beantwoord korte vragen, en ik kan je gegevens doorsturen zodat Sophie je terugbelt of mailt. Waarmee kan ik helpen?';

	function addMsg(cls, text) { var d = document.createElement('div'); d.className = 'ls-msg ' + cls; d.textContent = text; log.appendChild(d); log.scrollTop = 1e9; return d; }
	function setReplies(list) { sugg.innerHTML = list.map(function (r) { return '<button type="button">' + esc(r) + '</button>'; }).join(''); sugg.hidden = !list.length; }
	function bot(text, replies) { addMsg('bot', text); setReplies(replies || []); }
	function open() { panel.hidden = false; fab.hidden = true; fab.setAttribute('aria-expanded', 'true'); if (!log.children.length) { bot(GREET, MENU); } setTimeout(function () { try { input.focus(); } catch (e) {} }, 50); }
	function close() { panel.hidden = true; fab.hidden = false; fab.setAttribute('aria-expanded', 'false'); fab.focus(); }
	fab.addEventListener('click', open);
	root.querySelector('.ls-chat-x').addEventListener('click', close);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { close(); } });
	input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; });
	input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); } });
	sugg.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { handle(b.textContent); } });
	form.addEventListener('submit', function (e) { e.preventDefault(); var v = input.value.trim(); if (!v) { return; } input.value = ''; input.style.height = 'auto'; handle(v); });

	var STEPS = {
		naam: { ask: function () { bot('Fijn! Hoe heet je? (voor- en achternaam)'); }, take: function (v) { if (v.length < 2) { return 'Typ je naam even in.'; } chat.data.naam = v; return 'voor'; } },
		voor: { ask: function () { bot('Voor wie is de afspraak?', ['Mijn kind', 'Mezelf', 'Een familielid']); }, take: function (v) { chat.data.voor = v; return /kind/i.test(v) ? 'leeftijd' : 'hulp'; } },
		leeftijd: { ask: function () { bot('Hoe oud is je kind, of in welk leerjaar zit het?', ['Kleuter', '1e–2e leerjaar', '3e–4e leerjaar', '5e–6e leerjaar', 'Secundair']); }, take: function (v) { chat.data.leeftijd = v; return 'hulp'; } },
		hulp: { ask: function () { bot('Waarover gaat het?', HULP); }, take: function (v) { chat.data.hulp = v; return 'toel'; } },
		toel: { ask: function () { bot('Wil je nog iets kort toelichten? Bijvoorbeeld wat de school opmerkt. Medische details hoeven niet.', ['Overslaan']); }, take: function (v) { chat.data.toel = /^overslaan$/i.test(v) ? '' : v.slice(0, 500); return 'tel'; } },
		tel: { ask: function () { bot('Op welk telefoonnummer kan Sophie je bereiken?'); }, take: function (v) { if (v.replace(/[^\d]/g, '').length < 9) { return 'Dat telefoonnummer lijkt niet te kloppen. Probeer opnieuw, bv. 0470 12 34 56.'; } chat.data.tel = v; return 'mail'; } },
		mail: { ask: function () { bot('En je e-mailadres?'); }, take: function (v) { if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { return 'Dat e-mailadres lijkt niet te kloppen. Probeer opnieuw.'; } chat.data.mail = v; return 'pref'; } },
		pref: { ask: function () { bot('Hoe neemt Sophie het liefst contact op?', ['Bellen', 'Mailen', 'Maakt niet uit']); }, take: function (v) { chat.data.pref = v; return 'ok'; } },
		ok: {
			ask: function () { summary(); bot('Klopt dit? Als je op "Versturen" tikt, gaan deze gegevens naar Sophie. Ze worden alleen gebruikt om contact met je op te nemen.', ['Versturen', 'Aanpassen', 'Annuleren']); },
			take: function (v) {
				if (/versturen|^ja/i.test(v)) { send(); return null; }
				if (/aanpassen/i.test(v)) { chat.data = {}; return 'naam'; }
				bot('Geen probleem, er is niets verstuurd. Kan ik je nog ergens mee helpen?', MENU); return null;
			}
		},
		fwd: { ask: function () {}, take: function (v) { if (/^ja/i.test(v)) { return 'naamF'; } bot('Oké! Kan ik je nog ergens mee helpen?', MENU); return null; } },
		naamF: { ask: function () { bot('Hoe heet je?'); }, take: function (v) { if (v.length < 2) { return 'Typ je naam even in.'; } chat.data.naam = v; chat.data.voor = 'Vraag'; return 'tel'; } }
	};
	function go(step) { chat.step = step; if (step) { STEPS[step].ask(); } }
	function summary() {
		var d = chat.data, rows = [['Naam', d.naam], ['Voor', d.voor], ['Leeftijd', d.leeftijd], ['Hulpvraag', d.hulp], ['Toelichting', d.toel], ['Telefoon', d.tel], ['E-mail', d.mail], ['Contact via', d.pref]].filter(function (r) { return r[1]; });
		var c = document.createElement('div'); c.className = 'ls-sum';
		c.innerHTML = rows.map(function (r) { return '<b>' + esc(r[0]) + '</b><span>' + esc(r[1]) + '</span>'; }).join('');
		log.appendChild(c); log.scrollTop = 1e9;
	}
	function send() {
		setReplies([]);
		var wait = addMsg('bot wait', 'Bezig met versturen…');
		var d = chat.data; d.bron = 'chat'; d.toestemming = 1;
		post(d).then(function () {
			wait.remove();
			bot('Bedankt, ' + d.naam.split(' ')[0] + '! Je aanvraag is naar Sophie gestuurd. Ze neemt binnen ' + TERMIJN + ' contact op.', ['Nog een vraag']);
			chat.step = null; chat.data = {};
		}).catch(function (x) {
			wait.remove();
			addMsg('err', x.message + ' Je kan ook bellen naar ' + TEL + '.');
			setReplies(['Versturen', 'Annuleren']); chat.step = 'ok';
		});
	}
	function intent(v) {
		var t = v.toLowerCase();
		if (/afspraak|aanmeld|inschrijv|contact|terugbel|bellen/.test(t)) { return 'intake'; }
		if (/terugbet|riziv|ziekenfonds|mutualiteit|vergoed/.test(t)) { return 'terug'; }
		if (/prijs|tarief|kost|betalen/.test(t)) { return 'prijs'; }
		if (/wacht/.test(t)) { return 'wacht'; }
		if (/traject|verloop|werkwijze|voorschrift|bilan|stappen/.test(t)) { return 'traject'; }
		if (/portaal|oefenhoek|account|inlog|code/.test(t)) { return 'portaal'; }
		if (/shop|kopen|bestel|materiaal|boekje|avi/.test(t)) { return 'shop'; }
		if (/adres|waar|locatie|parkeer|telefoon|mail/.test(t)) { return 'adres'; }
		if (/volwassen|afasie|beroerte/.test(t)) { return 'volw'; }
		if (/^(hallo|hoi|dag|goedendag|hey)\b/.test(t)) { return 'hallo'; }
		return null;
	}
	function handle(v) {
		addMsg('me', v);
		if (chat.step) {
			var next = STEPS[chat.step].take(v.trim());
			if (next === null) { chat.step = null; return; }
			if (STEPS[next]) { go(next); } else { bot(next); }
			return;
		}
		if (/^nog een vraag$/i.test(v)) { bot('Waarmee kan ik nog helpen?', MENU); return; }
		if (/^iets anders vragen$/i.test(v)) { bot('Typ je vraag hieronder. Kan ik ze niet beantwoorden, dan stuur ik ze door naar Sophie.'); return; }
		var map = { 'wordt het terugbetaald?': 'terug', 'hoe verloopt een traject?': 'traject', 'wachttijd': 'wacht', 'oefenportaal': 'portaal', 'webshop': 'shop' };
		var k = map[v.toLowerCase()] || (/^afspraak aanvragen$/i.test(v) ? 'intake' : intent(v));
		if (k === 'intake') { chat.data = {}; go('naam'); return; }
		if (k === 'hallo') { bot('Hallo! Waarmee kan ik helpen?', MENU); return; }
		if (k && ANSWERS[k]) { bot(ANSWERS[k], ['Afspraak aanvragen', 'Nog een vraag']); return; }
		chat.data = { toel: v.slice(0, 500), hulp: 'Andere vraag' };
		bot('Daar heb ik geen antwoord op, maar Sophie wel. Zal ik je vraag samen met je gegevens naar haar doorsturen?', ['Ja, doorsturen', 'Nee, bedankt']);
		chat.step = 'fwd';
	}
})();
