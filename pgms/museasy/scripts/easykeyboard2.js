
var sound_profil = Synth.createInstrument("piano")

// Toutes les tonalités et les types d'accords utilisables
var tonalities = [
	[["C", "D", "E", "F", "G", "A", "B"], ["C", "D", "F", "G", "A"]],
	[["C", "D#", "E", "F", "G#", "A", "B"], ["C", "F", "A"]],
	[["C#", "D#", "E", "F", "F#", "G", "A", "B"], ["G", "A"]],
]
var chord_types =  ["Majeur", "Mineur", "SeptièmeDominante", "Diminué", "Quinte", "SeptièmeMajeure", "SeptièmeMineure"]

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
		// Début des types d'accords
		case 97:
			push_chord_type(chord_types[0])
			break
		case 98:
			push_chord_type(chord_types[1])
			break
		case 99:
			push_chord_type(chord_types[2])
			break
		case 100:
			push_chord_type(chord_types[3])
			break
		case 101:
			push_chord_type(chord_types[4])
			break
		case 102:
			push_chord_type(chord_types[5])
			break
		case 103:
			push_chord_type(chord_types[6])
			break
		case 104:
			break
		case 105:
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
            note_octave += 1
            break
        case 79:
            note_index = 1
            note_octave += 1
            break
        case 80:
            note_index = 2
            note_octave += 1
            break
        case 160:
            note_index = 3
            note_octave += 1
            break
        case 164:
            note_index = 4
            note_octave += 1
            break
        case 81:
            note_index = 5
            note_octave += 1
            break
        case 83:
            note_index = 6
            note_octave += 1
            break
        case 68:
            note_index = 0
            note_octave += 2
            break
        case 70:
            note_index = 1
            note_octave += 2
            break
        case 71:
            note_index = 2
            note_octave += 2
            break
        case 72:
            note_index = 3
            note_octave += 2
            break
        case 74:
            note_index = 4
            note_octave += 2
            break
        case 75:
            note_index = 5
            note_octave += 2
            break
        case 76:
            note_index = 6
            note_octave += 2
            break
         case 77:
            note_index = 0
            note_octave += 3
            break
        case 165:
            note_index = 1
            note_octave += 3
            break
        case 170:
            note_index = 2
            note_octave += 3
            break
        case 60:
            note_index = 3
            note_octave += 3
            break
        case 87:
            note_index = 4
            note_octave += 3
            break
        case 88:
            note_index = 5
            note_octave += 3
            break
        case 67:
            note_index = 6
            note_octave += 3
            break
        case 86:
            note_index = 0
            note_octave += 4
            break
        case 66:
            note_index = 1
            note_octave += 4
            break
        case 78:
            note_index = 2
            note_octave += 4
            break
        case 188:
            note_index = 3
            note_octave += 4
            break
        case 59:
            note_index = 4
            note_octave += 4
            break
        case 58:
            note_index = 5
            note_octave += 4
            break
        case 161:
            note_index = 6
            note_octave += 4
            break
		// Fin des notes
		default:
			// nothing
	}

	// Si une touche de note a été appuyée, sa valeur n'est plus -1, il faut donc la jouer
	if (note_index != -1) {
		play_note(note_index, note_octave)

		// Si une ou des touches de type d'accord est.sont appuyée.s, jouer d'autres notes selon le dernier accord appuyé
		if (chord_types_pressed.length > 0) {
			switch (chord_types_pressed[chord_types_pressed.length - 1]) {
				case chord_types[0]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					break
				case chord_types[1]:
					play_note(note_index + 1.5, note_octave)
					play_note(note_index + 4, note_octave)
					break
				case chord_types[2]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 5.5, note_octave)
					break
				case chord_types[3]:
					play_note(note_index + 1.5, note_octave)
					play_note(note_index + 3.5, note_octave)
					break
				case chord_types[4]:
					play_note(note_index + 4, note_octave)
					break
				case chord_types[5]:
					play_note(note_index + 2, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 6, note_octave)
					break
				case chord_types[6]:
					play_note(note_index + 1.5, note_octave)
					play_note(note_index + 4, note_octave)
					play_note(note_index + 5.5, note_octave)			
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
		// Début des types d'accords
		case 97:
			pull_chord_type(chord_types[0])
			break
		case 98:
			pull_chord_type(chord_types[1])
			break
		case 99:
			pull_chord_type(chord_types[2])
			break
		case 100:
			pull_chord_type(chord_types[3])
			break
		case 101:
			pull_chord_type(chord_types[4])
			break
		case 102:
			pull_chord_type(chord_types[5])
			break
		case 103:
			pull_chord_type(chord_types[6])
			break
		case 104:
			pull_chord_type(chord_types[7])
			break
		case 105:
			pull_chord_type(chord_types[8])
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