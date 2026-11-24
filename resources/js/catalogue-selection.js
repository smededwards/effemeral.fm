// Keep catalogue selection behavior in the compiled asset so Blade remains markup-only.
const selectAll = document.querySelector('#catalogue-select-all');
const albums = [...document.querySelectorAll('.catalogue-album-checkbox')];

if (selectAll && albums.length > 0) {
    const updateSelectAll = () => {
        const selected = albums.filter((album) => album.checked).length;
        selectAll.checked = selected === albums.length;
        selectAll.indeterminate = selected > 0 && selected < albums.length;
    };

    selectAll.addEventListener('change', () => {
        albums.forEach((album) => {
            album.checked = selectAll.checked;
        });
    });
    albums.forEach((album) => album.addEventListener('change', updateSelectAll));
}
