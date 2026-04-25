import Swal from 'sweetalert2'

export function useConfirm() {
    function css(variable) {
        return getComputedStyle(document.documentElement).getPropertyValue(variable).trim()
    }

    async function confirmDelete(itemName) {
        const result = await Swal.fire({
            title: '¿Eliminar registro?',
            html: `<span style="color:${css('--color-text-secondary')}">Se eliminará <strong style="color:${css('--color-text-primary')}">${itemName}</strong>. Esta acción no se puede deshacer.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            customClass: {
                popup:         'swal-popup',
                title:         'swal-title',
                confirmButton: 'swal-confirm',
                cancelButton:  'swal-cancel',
                icon:          'swal-icon',
            },
            buttonsStyling: false,
            background: css('--color-bg-card'),
            color: css('--color-text-primary'),
        })
        return result.isConfirmed
    }

    return { confirmDelete }
}
