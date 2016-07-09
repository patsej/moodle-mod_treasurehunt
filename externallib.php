<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External treasurehunt API
 *
 * @package   mod_treasurehunt
 * @copyright 2016 onwards Adrian Rodriguez Fernandez <huorwhisp@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

class mod_treasurehunt_external extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function fetch_treasurehunt_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetch_treasurehunt_parameters() {
        return new external_function_parameters(
                array(
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
                )
        );
    }

    public static function fetch_treasurehunt_returns() {
        return new external_single_structure(
                array(
            'treasurehunt' => new external_single_structure(
                    array(
                'stages' => new external_value(PARAM_RAW, 'geojson with all stages of the treasurehunt'),
                'roads' => new external_value(PARAM_RAW, 'json with all roads of the stage'))),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function fetch_treasurehunt($treasurehuntid) { //Don't forget to set it as static
        $params = self::validate_parameters(self::fetch_treasurehunt_parameters(),
                        array('treasurehuntid' => $treasurehuntid));

        $treasurehunt = array();
        $status = array();

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        list($treasurehunt['stages'], $treasurehunt['roads']) = treasurehunt_get_all_roads_and_stages($params['treasurehuntid'],
                $context);
        $status['code'] = 0;
        $status['msg'] = 'La caza del tesoro se ha cargado con éxito';

        $result = array();
        $result['treasurehunt'] = $treasurehunt;
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function update_stages_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_stages_parameters() {
        return new external_function_parameters(
                array(
            'stages' => new external_value(PARAM_RAW, 'GeoJSON with all stages to update'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function update_stages_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function update_stages($stages, $treasurehuntid, $lockid) { //Don't forget to set it as static
        global $DB;
        $params = self::validate_parameters(self::update_stages_parameters(),
                        array('stages' => $stages, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
        //Recojo todas las features

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        $features = treasurehunt_geojson_to_object($params['stages']);
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            try {
                $transaction = $DB->start_delegated_transaction();
                foreach ($features as $feature) {
                    treasurehunt_update_geometry_and_position_of_stage($feature, $context);
                }
                $transaction->allow_commit();
                $status['code'] = 0;
                $status['msg'] = 'La actualización de las etapas se ha realizado con éxito';
            } catch (Exception $e) {
                $transaction->rollback($e);
                $status['code'] = 1;
                $status['msg'] = $e;
            }
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }
        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_stage_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_stage_parameters() {
        return new external_function_parameters(
                array(
            'stageid' => new external_value(PARAM_RAW, 'id of stage'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_stage_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_stage($stageid, $treasurehuntid, $lockid) { //Don't forget to set it as static
        $params = self::validate_parameters(self::delete_stage_parameters(),
                        array('stageid' => $stageid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editstage', $context);
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            treasurehunt_delete_stage($params['stageid'], $context);
            $status['code'] = 0;
            $status['msg'] = 'La eliminación de la etapa se ha realizado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_road_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_road_parameters() {
        return new external_function_parameters(
                array(
            'roadid' => new external_value(PARAM_INT, 'id of road'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_road_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_road($roadid, $treasurehuntid, $lockid) { //Don't forget to set it as static
        global $DB;
        $params = self::validate_parameters(self::delete_road_parameters(),
                        array('roadid' => $roadid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        require_capability('mod/treasurehunt:editroad', $context);
        if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
            treasurehunt_delete_road($params['roadid'], $treasurehunt, $context);
            $status['code'] = 0;
            $status['msg'] = 'El camino se ha eliminado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

    public static function renew_lock_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function renew_lock_parameters() {
        return new external_function_parameters(
                array(
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock', VALUE_OPTIONAL)
                )
        );
    }

    public static function renew_lock_returns() {
        return new external_single_structure(
                array(
            'lockid' => new external_value(PARAM_INT, 'id of lock'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function renew_lock($treasurehuntid, $lockid) { //Don't forget to set it as static
        GLOBAL $USER;
        $params = self::validate_parameters(self::renew_lock_parameters(),
                        array('treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managetreasurehunt', $context);
        if (isset($params['lockid'])) {
            if (treasurehunt_edition_lock_id_is_valid($params['lockid'])) {
                $lockid = treasurehunt_renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha renovado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
            }
        } else {
            if (!treasurehunt_is_edition_loked($params['treasurehuntid'], $USER->id)) {
                $lockid = treasurehunt_renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha creado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'La caza del tesoro está siendo editada';
            }
        }
        $result = array();
        $result['status'] = $status;
        $result['lockid'] = $lockid;
        return $result;
    }

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function user_progress_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function user_progress_parameters() {
        return new external_function_parameters(
                array(
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'attempttimestamp' => new external_value(PARAM_INT,
                    'last known timestamp since user\'s progress has not been updated'),
            'roadtimestamp' => new external_value(PARAM_INT, 'last known timestamp since the road has not been updated'),
            'playwithoutmoving' => new external_value(PARAM_BOOL, 'If true the play mode is without move.'),
            'groupmode' => new external_value(PARAM_BOOL, 'If true the game is in groups.'),
            'initialize' => new external_value(PARAM_BOOL, 'If the map is initializing', VALUE_DEFAULT, false),
            'location' => new external_value(PARAM_RAW, "GeoJSON with point's location", VALUE_DEFAULT, 0),
            'selectedanswerid' => new external_value(PARAM_INT, "id of selected answer", VALUE_DEFAULT, 0),
            'qocremoved' => new external_value(PARAM_BOOL, 'If true question or acivity to end has been removed.'),
                )
        );
    }

    public static function user_progress_returns() {
        return new external_single_structure(
                array(
            'stages' => new external_value(PARAM_RAW, 'Geojson with all stages of the user/group'),
            'attempttimestamp' => new external_value(PARAM_INT, 'Last updated timestamp attempt'),
            'roadtimestamp' => new external_value(PARAM_INT, 'Last updated timestamp road'),
            'infomsg' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'The info text of attempt'),
                    'Array with all strings with attempts since the last stored timestamp'),
            'lastsuccessfulstage' => new external_single_structure(
                    array(
                'id' => new external_value(PARAM_INT, 'The id of the last successful stage.'),
                'position' => new external_value(PARAM_INT, 'The position of the last successful stage.'),
                'name' => new external_value(PARAM_RAW, 'The name of the last successful stage.'),
                'clue' => new external_value(PARAM_RAW, 'The clue of the last successful stage.'),
                'question' => new external_value(PARAM_RAW, 'The question of the last successful stage.'),
                'answers' => new external_multiple_structure(new external_single_structure(
                        array(
                    'id' => new external_value(PARAM_INT, 'The id of answer'),
                    'answertext' => new external_value(PARAM_RAW, 'The text of answer')
                        )), 'Array with all answers of the last successful stage.'),
                'totalnumber' => new external_value(PARAM_INT, 'The total number of stages on the road.'),
                'activitysolved' => new external_value(PARAM_BOOL, 'If true the activity to end is solved.')
                    ), 'object with data from the last successful stage', VALUE_OPTIONAL),
            'roadfinished' => new external_value(PARAM_RAW, 'If true the road is finished.'),
            'available' => new external_value(PARAM_BOOL, 'If true the hunt is available.'),
            'playwithoutmoving' => new external_value(PARAM_BOOL, 'If true the play mode is without move.'),
            'groupmode' => new external_value(PARAM_BOOL, 'If true the game is in groups.'),
            'historicalattempts' => new external_multiple_structure(
                    new external_single_structure(
                    array(
                'string' => new external_value(PARAM_TEXT, 'The info text of attempt'),
                'penalty' => new external_value(PARAM_BOOL, 'If true the attempt is penalized')
                    )
                    ), 'Array with user/group historical attempts.'),
            'qocremoved' => new external_value(PARAM_BOOL, 'If true question or acivity to end has been removed.'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function user_progress($treasurehuntid, $attempttimestamp, $roadtimestamp, $playwithoutmoving,
            $groupmode, $initialize, $location, $selectedanswerid, $qocremoved) { //Don't forget to set it as static
        global $USER, $DB;

        $params = self::validate_parameters(self::user_progress_parameters(),
                        array('treasurehuntid' => $treasurehuntid,
                    "attempttimestamp" => $attempttimestamp, "roadtimestamp" => $roadtimestamp,
                    'playwithoutmoving' => $playwithoutmoving, 'groupmode' => $groupmode, 'initialize' => $initialize,
                    'location' => $location, 'selectedanswerid' => $selectedanswerid, 'qocremoved' => $qocremoved));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:play', $context, null, false);
        // Recojo el grupo y camino al que pertenece
        $userparams = treasurehunt_get_user_group_and_road($USER->id, $treasurehunt, $cm->id);
        // Recojo el numero total de etapas del camino del usuario.
        $nostages = treasurehunt_get_total_stages($userparams->roadid);
        // Compruebo si el usuario ha finalizado el camino.
        $roadfinished = treasurehunt_check_if_user_has_finished($USER->id, $userparams->groupid, $userparams->roadid);
        $changesingroupmode = false;
        $qocremoved = $params['qocremoved'];
        if ($params['groupmode'] != $treasurehunt->groupmode) {
            $changesingroupmode = true;
        }
        // Recojo la info de las nuevas etapas descubiertas en caso de existir y los nuevos timestamp si han variado.
        $updates = treasurehunt_check_attempts_updates($params['attempttimestamp'], $userparams->groupid, $USER->id,
                $userparams->roadid, $changesingroupmode);
        if ($updates->newroadtimestamp != $params['roadtimestamp']) {
            $updateroad = true;
        } else {
            $updateroad = false;
        }
        $changesinplaymode = false;
        if ($params['playwithoutmoving'] != $treasurehunt->playwithoutmoving) {
            $changesinplaymode = true;
            if ($treasurehunt->playwithoutmoving) {
                $updates->strings[] = get_string('changetoplaywithmove', 'treasurehunt');
            } else {
                $updates->strings[] = get_string('changetoplaywithoutmoving', 'treasurehunt');
            }
        }
        $available = treasurehunt_is_available($treasurehunt);

        if ($available->available) {
            // Compruebo si se ha acertado la etapa y completado la actividad requerida.
            $qocsolved = treasurehunt_check_question_and_activity_solved($params['selectedanswerid'], $USER->id,
                    $userparams->groupid, $userparams->roadid, $updateroad, $context, $treasurehunt, $nostages,
                    $qocremoved);
            if ($qocsolved->msg !== '') {
                $status['msg'] = $qocsolved->msg;
                $status['code'] = 0;
            }
            if (count($qocsolved->updates)) {
                $updates->strings = array_merge($updates->strings, $qocsolved->updates);
            }
            // Compruebo si se han producido cambios
            if ($qocsolved->newattempt) {
                $updates->newattempttimestamp = $qocsolved->attempttimestamp;
            }
            if ($qocsolved->attemptsolved) {
                $updates->attemptsolved = true;
            }
            if ($qocsolved->roadfinished) {
                $roadfinished = true;
            }
            $qocremoved = $qocsolved->qocremoved;
        }
        // Compruebo si se ha enviado una localizacion y a la vez otro usuario del grupo no ha acertado ya esa etapa. 
        if (!$updates->geometrysolved && $params['location'] && !$updateroad && !$roadfinished && $available
                && !$changesinplaymode && !$changesingroupmode) {
            $checklocation = treasurehunt_check_user_location($USER->id, $userparams->groupid, $userparams->roadid,
                    treasurehunt_geojson_to_object($params['location']), $context, $treasurehunt, $nostages);
            if ($checklocation->newattempt) {
                $updates->newattempttimestamp = $checklocation->attempttimestamp;
                $updates->newgeometry = true;
            }
            if ($checklocation->newstage) {
                $updates->geometrysolved = true;
            }
            if ($checklocation->roadfinished) {
                $roadfinished = true;
            }
            if ($checklocation->update !== '') {
                $updates->strings[] = $checklocation->update;
            }
            $status['msg'] = $checklocation->msg;
            $status['code'] = 0;
        }


        $historicalattempts = array();
        // Si se ha producido cualquier nuevo intento recargo el historial de intentos.
        if ($updates->newattempttimestamp != $params['attempttimestamp'] || $params['initialize']) {
            $historicalattempts = treasurehunt_get_user_historical_attempts($userparams->groupid, $USER->id,
                    $userparams->roadid);
        }
        $lastsuccessfulstage = array();
        // Si se ha acertado una nueva localizacion, se ha modificado el camino, está fuera de tiempo,
        // se esta inicializando,se ha resuelto una pregunta o actividad o se ha cambiado el modo grupo.
        if ($updates->geometrysolved || !$available->available || $updateroad || $updates->attemptsolved
                || $params['initialize'] || $changesingroupmode) {
            $lastsuccessfulstage = treasurehunt_get_last_successful_stage($USER->id, $userparams->groupid,
                    $userparams->roadid, $nostages, $available->outoftime, $available->actnotavailableyet,
                    $roadfinished, $context);
        }
        // Si se han realizado un nuevo intento de localización o se esta inicializando
        if ($updates->newgeometry || $updateroad || $roadfinished || $params['initialize'] || $changesingroupmode) {
            $userstages = treasurehunt_get_user_progress($userparams->roadid, $userparams->groupid, $USER->id,
                    $treasurehuntid, $context);
        }
        // Si se ha editado el camino aviso.
        if ($updateroad) {
            if ($params['location']) {
                $status['msg'] = get_string('errsendinglocation', 'treasurehunt');
                $status['code'] = 1;
            }
            if ($params['selectedanswerid']) {
                $status['msg'] = get_string('errsendinganswer', 'treasurehunt');
                $status['code'] = 1;
            }
        }
        // Si esta fuera de tiempo y no está inicializando aviso.
        if ($available->outoftime && !$params['initialize']) {
            $updates->strings[] = get_string('timeexceeded', 'treasurehunt');
        }
        // Si la fecha de inicio aún no ha comenzado
        if ($available->actnotavailableyet) {
            $updates->strings[] = get_string('actnotavailableyet', 'treasurehunt');
        }
        if (!$status) {
            $status['msg'] = get_string('userprogress', 'treasurehunt');
            $status['code'] = 0;
        }

        $result = array();
        $result['infomsg'] = $updates->strings;
        $result['attempttimestamp'] = $updates->newattempttimestamp;
        $result['roadtimestamp'] = $updates->newroadtimestamp;
        $result['status'] = $status;
        $result['stages'] = $userstages;
        if ($lastsuccessfulstage) {
            $result['lastsuccessfulstage'] = $lastsuccessfulstage;
        }
        $result['roadfinished'] = $roadfinished;
        $result['available'] = $available->available;
        $result['playwithoutmoving'] = intval($treasurehunt->playwithoutmoving);
        $result['groupmode'] = intval($treasurehunt->groupmode);
        $result['historicalattempts'] = $historicalattempts;
        $result['qocremoved'] = $qocremoved;
        return $result;
    }

}
