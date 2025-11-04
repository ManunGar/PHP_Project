$(document).ready(function () {
    /*tinymce.init({
     selector: '.textarea',
     language: 'es',
     plugins: 'link',
     menubar: '',
     toolbar: 'undo redo | bold italic underline strikethrough | fontselect fontsizeselect formatselect | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist checklist | forecolor backcolor casechange permanentpen formatpainter removeformat | pagebreak | charmap emoticons | fullscreen  preview save print | insertfile image media pageembed template link anchor codesample | a11ycheck ltr rtl | showcomments addcomment',
     
     });*/

    // Obtener todos los elementos con la clase .textarea
    const textareas = document.querySelectorAll('.textarea');

    // Iterar sobre cada elemento y crear un editor CKEditor para cada uno
    textareas.forEach(textarea => {
        ClassicEditor
                .create(textarea, {
                    fontSize: {
                        options: [
                            'tiny',
                            'default',
                            'big'
                        ]
                    },
                    alignment: {
                        options: [ 'left', 'center', 'right' ]
                    },
                    toolbar: {
                        items: [
                            'undo',
                            'redo',
                            '|',
                            'bold',
                            'italic',
                            '|',
                            'bulletedList',
                            'numberedList',
                            'link',
                            '|',
                            'alignment',
                            'fontSize',
                            'heading',
                            'insertTable'
                        ],
                        shouldNotGroupWhenFull: true},
                    language: 'es'
                })
                .catch(error => {
                    console.error(error);
                });
    });
});