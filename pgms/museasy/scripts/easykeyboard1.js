
var sound_profil = Synth.createInstrument("piano")

// Toutes les tonalités et les types d'accords utilisables
var tonalities = [
	[["C", "D", "E", "F", "G", "A", "B"], ["C", "D", "F", "G", "A"]],
	[["C", "D", "E", "F#", "G", "A", "B"], ["C", "D", "G", "A"]],
	[["C#", "D", "E", "F#", "G", "A", "B"], ["D", "G", "A"]],
	[["C#", "D", "E", "F#", "G#", "A", "B"], ["D", "A"]],
	[["C#", "D#", "E", "F#", "G#", "A", "B"], ["A"]],
	[["C#", "D#", "E", "F#", "G#", "A#", "B"], []],
	[["C#", "D#", "F", "F#", "G#", "A#", "B"], []],
	[["C#", "D#", "F", "F#", "G#", "A#", "C"], []],
]
var chord_types =  ["Majeur", "Mineur", "Diminué", "Augmenté", "QuarteSuspendue", "SecondeSuspendue", "SixteMajeure", "SixteMineure", "SeptièmeDominante", "SeptièmeMajeure", "SeptièmeMineure", "SeptièmeDominanteQuinteDiminuée", "SeptièmeDominanteQuinteAugmentée", "SeptièmeDiminuée"]

// La gamme, les dièses, l'octave et les types d'accords en cours d'utilisation
var active_gamme = tonalities[0][0]
var allowed_sharps = tonalities[0][1]
var active_octave = 3
var chord_types_pressed = []

/**
 * Quand une touche d'octave est pressée, changer l'octave active, en sachant que les octaves sont sur le pavé numérique, de 1 à 9
 * Quand une touche de type d'accord est pressée, ajouter ce type d'accord au tableau des accords, en sachant que les types d'accords sont de ² à backspace
 * Quand la touche appuyée correspond à une note, jouer cette note, en sachant qu'il y a une note sur chaque lettre du clavier, dans l'ordre A-P, Q-M, W-N
 * @param {*} event 
 */
function key_pressed(event) {
	event.preventDefault() // Anti-F5 et autres
	var key_id = event.keyCode
	//console.log("Touche appuyée : " + touche)

	// Variables utilisées dans le cas ou la touche pressée correspond à une note
	var note_index = -1 // Position de la note dans la gamme, de 0 à 6 (!= note_step, qui est la valeur de la note, en notation anglaise)
	var note_octave = active_octave // Octave de la note, car le clavier comporte plusieurs octaves (26 lettres -> 26 notes -> 3.7 octaves)

	switch (key_id) {
		// Début des octaves
		case 97:
			active_octave = 1
			break
		case 98:
			active_octave = 2
			break
		case 99:
			active_octave = 3
			break
		case 100:
			active_octave = 4
			break
		case 101:
			active_octave = 5
			break
		case 102:
			active_octave = 6
			break
		case 103:
			active_octave = 7
			break
		case 104:
			active_octave = 8
			break
		case 105:
			active_octave = 9
			break
		// Fin des octaves
		// Début des types d'accords
		case 0:
			push_chord_type(chord_types[0])
			break
		case 49:
			push_chord_type(chord_types[1])
			break
		case 50:
			push_chord_type(chord_types[2])
			break
		case 51:
			push_chord_type(chord_types[3])
			break
		case 52:
			push_chord_type(chord_types[4])
			break
		case 53:
			push_chord_type(chord_types[5])
			break
		case 54:
			push_chord_type(chord_types[6])
			break
		case 55:
			push_chord_type(chord_types[7])
			break
		case 56:
			push_chord_type(chord_types[8])
			break
		case 57:
			push_chord_type(chord_types[9])
			break
		case 48:
			push_chord_type(chord_types[10])
			break
		case 169:
			push_chord_type(chord_types[11])
			break
		case 61:
			push_chord_type(chord_types[12])
			break
		case 8:
			push_chord_type(chord_types[13])
			break
		// Fin des types d'accords
		// Début des notes
		case 65:
			note_index = 0
			break
		case 90:
			note_index = 1
			break
		case 69:
			note_index = 2
			break
		case 82:
			note_index = 3
			break
		case 84:
			note_index = 4
			break
		case 89:
			note_index = 5
			break
		case 85:
			note_index = 6
			break
		case 73:
			note_index = 0
			++note_octave
			break
		case 79:
			note_index = 1
			++note_octave
			break
		case 80:
			note_index = 2
			++note_octave
			break
		case 81:
			note_index = 3
			++note_octave
			break
		case 83:
			note_index = 4
			++note_octave
			break
		case 68:
			note_index = 5
			++note_octave
			break
		case 70:
			note_index = 6
			++note_octave
			break
		case 71:
			note_index = 0
			note_octave += 2
			break
		case 72:
			note_index = 1
			note_octave += 2
			break
		case 74:
			note_index = 2
			note_octave += 2
			break
		case 75:
			note_index = 3
			note_octave += 2
			break
		case 76:
			note_index = 4
			note_octave += 2
			break
		case 77:
			note_index = 5
			note_octave += 2
			break
		case 87:
			note_index = 6
			note_octave += 2
			break
		case 88:
			note_index = 0
			note_octave += 3
			break
		case 67:
			note_index = 1
			note_octave += 3
			break
		case 86:
			note_index = 2
			note_octave += 3
			break
		case 66:
			note_index = 3
			note_octave += 3
			break
		case 78:
			note_index = 4
			note_octave += 3
			break
		// Fin des notes
		default:
			// nothing
	}

	// Si une touche de note a été appuyée, sa valeur n'est plus -1, il faut donc la jouer
	if (note_index != -1) {
		play_note(note_index, note_octave)

		// Si une ou des touches de type d'accord est.sont appuyée.s, jouer d'autres notes
		for (var i = 0; i < chord_types_pressed.length; ++i) {
			switch (chord_types_pressed[i]) {
				case chord_types[0]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					break
				case chord_types[1]:
					play_note(note_index + 1.5, note_octave)
					play_note(note_index + 4, note_octave)
					break
				case chord_types[2]:
					play_note(note_index + 1.5, note_octave)
					play_note(note_index + 3.5, note_octave)
					break
				case chord_types[3]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4.5, note_octave)
					break
				case chord_types[4]:
					play_note(note_index + 3, note_octave)
					play_note(note_index + 4, note_octave)
					break
				case chord_types[5]:
					play_note(note_index + 1, note_octave)
					play_note(note_index + 4, note_octave)
					break
				case chord_types[6]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 5, note_octave)			
					break
				case chord_types[7]:
					play_note(note_index + 1.5, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 5, note_octave)
					break
				case chord_types[8]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 5.5, note_octave)
					break
				case chord_types[9]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 6, note_octave)
					break
				case chord_types[10]:
					play_note(note_index + 2.5, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 5.5, note_octave)
					break
				case chord_types[11]:
					break
				case chord_types[12]:
					break
				case chord_types[13]:
					break
				default:
					// nothing
			}
		}
	} // Fin du if d'une touche de note a été appuyée
}

/**
 * Quand une touche de type d'accord est relâchée, enlever ce type d'accord au tableau des accords, en sachant que les types d'accords sont de ² à backspace
 * @param {*} event 
 */
function key_released(event) {
	var key_id = event.keyCode
	//console.log("Touche relâchée : " + touche)

	switch (key_id) {
		case 0:
			pull_chord_type(chord_types[0])
			break
		case 49:
			pull_chord_type(chord_types[1])
			break
		case 50:
			pull_chord_type(chord_types[2])
			break
		case 51:
			pull_chord_type(chord_types[3])
			break
		case 52:
			pull_chord_type(chord_types[4])
			break
		case 53:
			pull_chord_type(chord_types[5])
			break
		case 54:
			pull_chord_type(chord_types[6])
			break
		case 55:
			pull_chord_type(chord_types[7])
			break
		case 56:
			pull_chord_type(chord_types[8])
			break
		case 57:
			pull_chord_type(chord_types[9])
			break
		case 48:
			pull_chord_type(chord_types[10])
			break
		case 169:
			pull_chord_type(chord_types[11])
			break
		case 61:
			pull_chord_type(chord_types[12])
			break
		case 8:
			pull_chord_type(chord_types[13])
			break
	}
}

/**
 * La lecture de la note est sous-traitée pour éviter de jouer la note d'un indice hors-octave
 * Si l'indice de la note est supérieur au nombre de notes contenues dans une octave, celle-ci augmente du reste de la division
 * Si l'indice de la note est supérieur au nombre de notes contenues dans une octave, celui-ci est ramené à un indice existant dans la ou les gamme.s suivante.s
 * Les notes dièses sont représentées par un nombre décimal, exemple 1.5 * 10 % 10 != 0 -> note dièse si valide dans la gamme
 * @param {*} index 
 * @param {*} ctave 
 */
function play_note(index, octave) {
	var play = true
	var sharp = ""

	// Mise à niveau de l'octave en cas d'indice grand (> nombre de notes dans la gamme)
	octave += index / active_gamme.length
	// Mise à niveau de l'indice de la note en cas d'indice trop grand (> nombre de notes dans la gamme)
	index = index % active_gamme.length

	// Traitement particulier en cas d'indice décimal (note dièse, ou note suivante dans la gamme si la dièse est invalide)
	if (index * 10 % 10 != 0) {
		// Situation par défaut : la note dièse existe pour cette gamme
		sharp = "#"
		index -= 0.5

		// Situation particulière : la note dièse n'existe pas, il faut jouer la note suivante dans la gamme
		if (allowed_sharps.indexOf(active_gamme[index]) === -1) {
			index += 1
			play = false
		}
	}

	// Lecture de la note
	if (play)
		sound_profil.play(active_gamme[index] + sharp, octave, 1)
	else
		play_note(index, octave)
}

function push_chord_type(chord_type) {
	if (chord_types_pressed.indexOf(chord_type) === -1)
		chord_types_pressed.push(chord_type)
}

function pull_chord_type(chord_type) {
	var chord_type_pos = chord_types_pressed.indexOf(chord_type)
	chord_types_pressed = chord_types_pressed.slice(0, chord_type_pos).concat(chord_types_pressed.slice(chord_type_pos, chord_types_pressed.length-1)) // [0, 1, 2], 1 à enlever => [0] concat [2]
}

function change_tonality(select) {
	active_gamme = tonalities[select.value][0]
	allowed_sharps = tonalities[select.value][1]
}

/*

-----
NOTES
=====

L'évènement OnKeyDown se déclenche lorsque la touche et appuyée, puis après queqlues millisecondes, se répète à intervalles de temps réguliers

*/