<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'tool_uploadcourse', language 'es', version '3.11'.
 *
 * @package     local_createcourse
 * @category    string
 * @copyright   1999 Martin Dougiamas and contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allowdeletes'] = 'Permitir eliminar';
$string['allowdeletes_help'] = 'Si está aceptado o no el campo eliminar.';
$string['allowrenames'] = 'Permitir renombrar';
$string['allowrenames_help'] = 'Si está aceptado o no el campo renombrar.';
$string['allowresets'] = 'Permitir reiniciar';
$string['allowresets_help'] = 'Si está aceptado o no el campo restablecer.';
$string['cachedef_helper'] = 'Almacenamiento en caché de la aplicación auxiliar';
$string['cannotdeletecoursenotexist'] = 'No se puede eliminar un curso que no existe';
$string['cannotforcelang'] = 'Sin permiso para forzar el idioma para este curso';
$string['cannotgenerateshortnameupdatemode'] = 'No se puede generar un nombre corto si se permiten las actualizaciones';
$string['cannotreadbackupfile'] = 'No se puede leer el archivo de copia de seguridad';
$string['cannotrenamecoursenotexist'] = 'No se puede renombrar un curso que no existe';
$string['cannotrenameidnumberconflict'] = 'No se puede renombrar el curso, hay conflicto con el número ID de un curso que ya existe';
$string['cannotrenameshortnamealreadyinuse'] = 'No se puede renombrar el curso, el nombre corto se está utilizando';
$string['cannotupdatefrontpage'] = 'Está prohibido modificar la página principal';
$string['canonlyrenameinupdatemode'] = 'Sólo puede renombrar un curso cuando la actualización está permitida';
$string['canonlyresetcourseinupdatemode'] = 'Sólo se puede reiniciar un curso en modo de actualización';
$string['couldnotresolvecatgorybyid'] = 'No se ha podido resolver la categoría por su ID';
$string['couldnotresolvecatgorybyidnumber'] = 'No se ha podido resolver la categoría por su número ID';
$string['couldnotresolvecatgorybypath'] = 'No se ha podido resolver la categoría por su path';
$string['coursecreated'] = 'Curso creado';
$string['coursedeleted'] = 'Curso eliminado';
$string['coursedeletionnotallowed'] = 'No está permitido eliminar curso';
$string['coursedoesnotexistandcreatenotallowed'] = 'El curso no existe y no está permitido crear cursos';
$string['courseexistsanduploadnotallowed'] = 'El curso existe y no está permitida la actualziación';
$string['coursefile'] = 'Archivo';
$string['coursefile_help'] = 'Debe ser un archivo CSV';
$string['courseidnumberincremented'] = 'Número ID del curso incrementado {$a->from} -> {$a->to}';
$string['courseprocess'] = 'Proceso del curso';
$string['courserenamed'] = 'Curso renombrado';
$string['courserenamingnotallowed'] = 'No está permitido renombrar cursos';
$string['coursereset'] = 'Curso reiniciado';
$string['courseresetnotallowed'] = 'No está permitido reiniciar curso';
$string['courserestored'] = 'Cursos restaurado';
$string['coursescreated'] = 'Cursos creados: {$a}';
$string['coursesdeleted'] = 'Cursos elimiados: {$a}';
$string['courseserrors'] = 'Cursos con errores: {$a}';
$string['courseshortnamegenerated'] = 'Nombre corto de cursos generado: {$a}';
$string['courseshortnameincremented'] = 'Nombre corto incrementado';
$string['coursestotal'] = 'Total de cursos: {$a}';
$string['coursesupdated'] = 'Cursos actualizados: {$a}';
$string['coursetemplatename'] = 'Restaurar de este curso después de subirlo';
$string['coursetemplatename_help'] = 'Introduzca un nombre corto del curso existente para usar como plantilla para la creación de todos los cursos.';
$string['coursetorestorefromdoesnotexist'] = 'El curso a restaurar no existe';
$string['courseupdated'] = 'Curso actualizado';
$string['createall'] = 'Crear todos, incrementar nombre corto si es necesario';
$string['createnew'] = 'Crear solo cursos nuevos, saltar los existentes';
$string['createorupdate'] = 'Crear cursos nuevos, o actualizar los existentes';
$string['csvdelimiter'] = 'Separador CSV';
$string['csvdelimiter_help'] = 'El carácter que separa las series de datos en cada registro.';
$string['csvfileerror'] = 'Hay algo erróneo en el formato del archivo CSV. Por favor, compruebe la coincidencia entre el número de cabeceras y columnas  y que el delimitador y la codificación del archivo sean correctos: {$a}';
$string['csvline'] = 'Línea';
$string['customfieldinvalid'] = 'El campo personalizado \'{$a}\' está vacío o contiene datos no válidos';
$string['defaultvalues'] = 'Valores por defecto del curso';
$string['defaultvaluescustomfieldcategory'] = 'Valores por defecto para \'{$a}\'';
$string['downloadcontentnotallowed'] = 'No se permite configurar la descarga del contenido del curso';
$string['encoding'] = 'Codificación';
$string['encoding_help'] = 'Codificación del archivo CSV';
$string['errorcannotcreateorupdateenrolment'] = 'No se puede crear o actualizar el método de matriculación \'{$a}\'';
$string['errorcannotdeleteenrolment'] = 'No se puede borrar el método de matriculación \'{$a}\'';
$string['errorcannotdisableenrolment'] = 'No se puede deshabilitar el método de matriculación \'{$a}\'';
$string['errorwhiledeletingcourse'] = 'Error al eliminar el curso';
$string['errorwhilerestoringcourse'] = 'Error al restaurar el curso';
$string['generatedshortnamealreadyinuse'] = 'El nombre corto generado ya está en uso';
$string['generatedshortnameinvalid'] = 'El nombre corto generado no es válida';
$string['id'] = 'ID';
$string['idnumberalreadyinuse'] = 'Número ID ya utilizado en otro curso';
$string['importoptions'] = 'Opciones de importación';
$string['invalidbackupfile'] = 'Archivo de copia de seguridad no válido';
$string['invalidcourseformat'] = 'Formato de curso no válido';
$string['invalidcsvfile'] = 'Archivo de entrada CSV no válido';
$string['invaliddownloadcontent'] = 'Descarga no válida del valor del contenido del curso';
$string['invalidencoding'] = 'Codificación no válida';
$string['invalideupdatemode'] = 'Modo de actualización seleccionado no válido';
$string['invalidfullnametoolong'] = 'El campo de nombre completo está limitado a {$a} caracteres';
$string['invalidmode'] = 'Modo seleccionado no válido';
$string['invalidroles'] = 'Nombres de rol no válidos: {$a}';
$string['invalidshortname'] = 'Nombre corto no válido';
$string['invalidshortnametoolong'] = 'El campo de nombre corto está limitado a {$a} caracteres';
$string['invalidvisibilitymode'] = 'Modo visible no válido';
$string['missingmandatoryfields'] = 'Falta valor para los campos obligatorios: {$a}';
$string['missingshortnamenotemplate'] = 'Falta nombre corto y la plantilla de nombre corto no se ha determinado';
$string['mode'] = 'Modo subida';
$string['mode_help'] = 'Esto le permite especificar si se pueden crear o actualizar los cursos.';
$string['nochanges'] = 'Sin cambios';
$string['pluginname'] = 'Crear cursos';
$string['preview'] = 'Vista previa';
$string['privacy:metadata'] = 'El complemento de carga de curso no almacena ningún dato personal.';
$string['reset'] = 'Reiniciar curso tras la subida';
$string['reset_help'] = 'Si se reiniciará el curso después de crearlo/actualizarlo.';
$string['restoreafterimport'] = 'Restaurar tras la importación';
$string['result'] = 'resultado';
$string['rowpreviewnum'] = 'Columnas de la vista previa';
$string['rowpreviewnum_help'] = 'Número de filas del archivo CSV que se previsualizarán en la siguiente página. Esta opción tiene como objetivo limitar el tamaño de la próxima página.';
$string['shortnamefullnameerror'] = 'El sufijo no coincide entre shortname \'{$a->shortname}\' y fullname \'{$a->fullname}\', busca por este shortname: \'{$a->shortname}\' - 1';
$string['shortnametemplate'] = 'Plantilla para generar un nombre corto';
$string['shortnametemplate_help'] = 'El nombre corto del curso se muestra en la barra de navegación. Usted puede utilizar la sintaxis de plantillas (%f = fullname, %i = idnumber) o introducir un valor inicial que se irá incrementando.';
$string['templatefile'] = 'Restaurar a partir de este archivo después de la carga';
$string['templatefile_help'] = 'Seleccionar un archivo para su uso como plantilla para la creación de todos los cursos.';
$string['unknownimportmode'] = 'Modo de importación desconocido';
$string['updatemissing'] = 'Rellene los elementos que faltan en los datos CSV y valores predeterminados';
$string['updatemode'] = 'Modo de actualización';
$string['updatemode_help'] = 'Si permite que se actualicen los cursos, también deberá especificar en la herramienta con qué actualizar los cursos.';
$string['updatemodedoessettonothing'] = 'El modo de actualización no permite que nada se actualice';
$string['updateonly'] = 'Sólo actualizar cursos existentes';
$string['updatewithdataonly'] = 'Actualizar solo los datos CSV';
$string['updatewithdataordefaults'] = 'Actualizar los datos CSV y los valores predeterminados';
$string['uploadcourses'] = '👉 Auto crear cursos';
$string['uploadcourses_help'] = 'Los cursos pueden ser cargados a través de archivos de texto. El formato del archivo debe ser el siguiente:

* Cada línea del archivo contiene un registro
* Cada registro es una serie de datos separados por comas (u otros delimitadores)
* El primer registro contiene una lista de nombres de campo que define el formato del resto del archivo
* los campos requeridos son nombre corto, nombre completo, el resumen y la categoría';
$string['uploadcoursespreview'] = 'Previsualización de los cursos cargados';
$string['uploadcoursesresult'] = 'Resultados de la carga de cursos';

$string['fecasita'] = '<img class="casita" src="/imagenes/home.png"></img>';

$string['mensajeautomatriculacion'] = '<p>Estimado/a {$a->fullname},</p>
<p>Te damos la bienvenida al curso {$a->coursename} en el que te has matriculado a través de Cursos Exprés.</p>
<p><strong>Acceder al curso es muy sencillo</strong>, el día y hora del curso sigue los siguientes pasos:</p>
<ol>
<li>Accede a la plataforma de formación <a href="https://aulaenlinea.justicia.es">aulaenlinea.justicia.es</a> con tu usuario de dominio y contraseña o con tu certificado digital.</li>
<li>En el apartado <strong>Mis cursos</strong> pincha sobre el curso {$a->coursename}.</li>
<li>En la pantalla que aparece selecciona la opción <strong>Continuar en este explorador</strong>.</li>
<li>A continuación, introduce tu <strong>nombre y DNI</strong> y pulsa en <strong>Unirse ahora</strong>.</li>
</ol>
<p>Te recomendamos que accedas con unos minutos de antelación al inicio de la formación.</p>
<p>Para cualquier duda y/o consulta recuerda que cuentas con un formador para resolver todas tus dudas en el <strong>Servicio de Formación Exprés</strong> a través de la <strong>opción 3 del teléfono 91 385 80 00</strong>.</p>';

$string['mensajeautomatriculacionwebinar'] = '<p>Estimado/a {$a->fullname},</p>
<p>Te damos la bienvenida al curso {$a->coursename} en el que te has matriculado a través de Aula en línea.</p>
<p><strong>Acceder al curso es muy sencillo</strong>, el día y hora del curso sigue los siguientes pasos:</p>
<ol>
<li>Accede a la plataforma de formación <a href="https://aulaenlinea.justicia.es">aulaenlinea.justicia.es</a> con tu usuario de dominio y contraseña o con tu certificado digital.</li>
<li>En el apartado <strong>Mis cursos</strong> pincha sobre el curso {$a->coursename}.</li>
<li>En la pantalla que aparece selecciona la opción <strong>Continuar en este explorador</strong>.</li>
<li>A continuación, introduce tu <strong>nombre y DNI</strong> y pulsa en <strong>Unirse ahora</strong>.</li>
</ol>
<p>Te recomendamos que accedas con unos minutos de antelación al inicio de la formación.</p>
<p>Para cualquier duda y/o consulta recuerda que cuentas con un formador para resolver todas tus dudas.</p>';
//Errores
$string['missingcategoryfield'] = 'No hay categoria y se creará una con este nombre';
