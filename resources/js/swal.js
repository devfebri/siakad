// SweetAlert dimuat saat pertama dipakai; `Swal.fire(...)` tetap mengembalikan promise seperti biasa.
let muat;

export default {
    fire: (...args) => (muat ??= import('sweetalert2')).then((m) => m.default.fire(...args)),
};
