/** Knockout invoque les handlers avec this = $data du contexte courant
 *  (item dans un foreach / objet dans un with) : on force le binding sur le VM. */
export function bindAll(vm, proto) {
	for (const m of Object.getOwnPropertyNames(proto)) {
		if (m !== 'constructor' && typeof vm[m] === 'function') {
			vm[m] = vm[m].bind(vm);
		}
	}
}
