<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/treasurehunt/locallib.php");

class mod_treasurehunt_external_fetch_treasurehunt extends external_api {

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
                'riddles' => new external_value(PARAM_RAW, 'geojson with all riddles of the treasurehunt'),
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
        $params = self::validate_parameters(self::fetch_treasurehunt_parameters(), array('treasurehuntid' => $treasurehuntid));

        $treasurehunt = array();
        $status = array();

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:gettreasurehunt', $context);
        list($treasurehunt['riddles'], $treasurehunt['roads']) = get_treasurehunt($params['treasurehuntid'], $context);
        $status['code'] = 0;
        $status['msg'] = 'La caza del tesoro se ha cargado con éxito';

        $result = array();
        $result['treasurehunt'] = $treasurehunt;
        $result['status'] = $status;
        return $result;
    }

}

class mod_treasurehunt_external_update_riddles extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function update_riddles_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_riddles_parameters() {
        return new external_function_parameters(
                array(
            'riddles' => new external_value(PARAM_RAW, 'GeoJSON with all riddles to update'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function update_riddles_returns() {
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
    public static function update_riddles($riddles, $treasurehuntid, $lockid) { //Don't forget to set it as static
        global $DB, $USER;
        $params = self::validate_parameters(self::update_riddles_parameters(), array('riddles' => $riddles, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managescavenger', $context);
        require_capability('mod/treasurehunt:editriddle', $context);
        $features = geojson_to_object($params['riddles']);
        if (edition_lock_id_is_valid($params['lockid'])) {
            try {
                $transaction = $DB->start_delegated_transaction();
                foreach ($features as $feature) {
                    update_geometry_and_position_of_riddle($feature);
                    // Trigger update riddle event.
                    $eventparams = array(
                        'context' => $context,
                        'objectid' => $feature->getId()
                    );
                    \mod_treasurehunt\event\riddle_updated::create($eventparams)->trigger();
                }
                $transaction->allow_commit();
                $status['code'] = 0;
                $status['msg'] = 'La actualización de las pistas se ha realizado con éxito';
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

}

class mod_treasurehunt_external_delete_riddle extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_riddle_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_riddle_parameters() {
        return new external_function_parameters(
                array(
            'riddleid' => new external_value(PARAM_RAW, 'id of riddle'),
            'treasurehuntid' => new external_value(PARAM_INT, 'id of treasurehunt'),
            'lockid' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_riddle_returns() {
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
    public static function delete_riddle($riddleid, $treasurehuntid, $lockid) { //Don't forget to set it as static
        $params = self::validate_parameters(self::delete_riddle_parameters(), array('riddleid' => $riddleid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managescavenger', $context);
        require_capability('mod/treasurehunt:editriddle', $context);
        if (edition_lock_id_is_valid($params['lockid'])) {
            delete_riddle($params['riddleid']);
            // Trigger deleted riddle event.
            $eventparams = array(
                'context' => $context,
                'objectid' => $params['riddleid'],
            );
            \mod_treasurehunt\event\riddle_deleted::create($eventparams)->trigger();
            $status['code'] = 0;
            $status['msg'] = 'La eliminación de la pista se ha realizado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_treasurehunt_external_delete_road extends external_api {

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
        $params = self::validate_parameters(self::delete_road_parameters(), array('roadid' => $roadid, 'treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managescavenger', $context);
        require_capability('mod/treasurehunt:editroad', $context);
        if (edition_lock_id_is_valid($params['lockid'])) {
            delete_road($params['roadid']);
            // Trigger deleted road event.
            $eventparams = array(
                'context' => $context,
                'objectid' => $params['roadid']
            );
            \mod_treasurehunt\event\road_deleted::create($eventparams)->trigger();
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

}

class mod_treasurehunt_external_renew_lock extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
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
        $params = self::validate_parameters(self::renew_lock_parameters(), array('treasurehuntid' => $treasurehuntid, 'lockid' => $lockid));

        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:managescavenger', $context);
        if (isset($params['lockid'])) {
            if (edition_lock_id_is_valid($params['lockid'])) {
                $lockid = renew_edition_lock($params['treasurehuntid'], $USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha renovado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
            }
        } else {
            if (!is_edition_loked($params['treasurehuntid'], $USER->id)) {
                $lockid = renew_edition_lock($params['treasurehuntid'], $USER->id);
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

}

class mod_treasurehunt_external_user_progress extends external_api {

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
            'attempttimestamp' => new external_value(PARAM_INT, 'last known timestamp since user\'s progress has not been updated'),
            'roadtimestamp' => new external_value(PARAM_INT, 'last known timestamp since the road has not been updated'),
            'initialize' => new external_value(PARAM_BOOL, 'If the map is initializing', VALUE_DEFAULT, false),
            'location' => new external_value(PARAM_RAW, "GeoJSON with point's location", VALUE_DEFAULT, 0),
            'selectedanswerid' => new external_value(PARAM_INT, "id of selected answer", VALUE_DEFAULT, 0))
        );
    }

    public static function user_progress_returns() {
        return new external_single_structure(
                array(
            'riddles' => new external_value(PARAM_RAW, 'geojson with all riddles of the user/group'),
            'attempttimestamp' => new external_value(PARAM_INT, 'last updated timestamp attempt'),
            'roadtimestamp' => new external_value(PARAM_INT, 'last updated timestamp road'),
            'infomsg' => new external_value(PARAM_RAW, 'array with all strings with attempts since the last stored timestamp'),
            'lastsuccesfulriddle' => new external_single_structure(
                    array(
                'id' => new external_value(PARAM_INT, 'The id of the last successful riddle.'),
                'number' => new external_value(PARAM_INT, 'The number of the last successful riddle.'),
                'name' => new external_value(PARAM_RAW, 'The name of the last successful riddle.'),
                'description' => new external_value(PARAM_RAW, 'The description of the last successful riddle.'),
                'question' => new external_value(PARAM_RAW, 'The question of the last successful riddle.'),
                'answers' => new external_value(PARAM_RAW, 'The answers of the last successful riddle.')), 'object with data from the last successful riddle', VALUE_OPTIONAL),
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
    public static function user_progress($treasurehuntid, $attempttimestamp, $roadtimestamp, $initialize, $location, $selectedanswerid) { //Don't forget to set it as static
        global $USER, $DB;

        $params = self::validate_parameters(self::user_progress_parameters(), array('treasurehuntid' => $treasurehuntid,
                    "attempttimestamp" => $attempttimestamp, "roadtimestamp" => $roadtimestamp, 'initialize' => $initialize,
                    'location' => $location, 'selectedanswerid' => $selectedanswerid));
        $cm = get_coursemodule_from_instance('treasurehunt', $params['treasurehuntid']);
        $treasurehunt = $DB->get_record('treasurehunt', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/treasurehunt:play', $context);
        // Recojo el grupo y camino al que pertenece
        $userparams = get_user_group_and_road($USER->id, $cm);
        // Recojo el numero total de pistas del camino del usuario.
        $noriddles = get_total_riddles($userparams->roadid);
        // Recojo la info de las nuevas pistas descubiertas en caso de existir y los nuevos timestamp si han variado.
        list($newattempttimestamp,
                $newroadtimestamp,
                $updates,
                $geometrysolved,
                $newgeometry,
                $newroadassigned) = check_attempts_updates($params['attempttimestamp'],$userparams->groupid, $USER->id, $userparams->roadid);
        if ($newroadtimestamp != $params['roadtimestamp']) {
            $updateroad = true;
        } else {
            $updateroad = false;
        }


        // Compruebo si se ha enviado una localizacion y a la vez otro usuario del grupo no ha acertado ya esa pista. 
        if (!$geometrysolved && $params['location'] && !$updateroad && !$params['selectedanswerid']) {
            $checklocation = check_user_location($USER->id, $userparams->groupid, $userparams->roadid, geojson_to_object($params['location']), $context, $treasurehunt, $noriddles);
            $newattempttimestamp = $checklocation->attempttimestamp;
            // Si se ha descubierto una nueva pista recojo el nuevo id.
            if ($checklocation->newriddle) {
                $geometrysolved = true;
            }
            $newgeometry = true;
            $status['msg'] = $checklocation->msg;
            $status['code'] = 0;
        }

        if (!$params['location']) {
            // Compruebo si se ha acertado la pista y completado la actividad requerida.
            $qocsolved = check_question_and_completion_solved($params['selectedanswerid'], $USER->id, $userparams->groupid, $userparams->roadid, $updateroad, $context, $treasurehunt, $noriddles);
            if ($qocsolved->msg !== '') {
                $status['msg'] = $qocsolved->msg;
                $status['code'] = 0;
            }
            // Compruebo si se han producido cambios
            if ($qocsolved->newattempt) {
                $newattempttimestamp = $qocsolved->attempttimestamp;
            }
        }

        $historicalattempts = array();
        // Si se ha producido cualquier nuevo intento recargo el historial de intentos.
        if ($newattempttimestamp != $params['attempttimestamp'] || $params['initialize']) {
            $historicalattempts = get_user_historical_attempts($userparams->groupid, $USER->id, $userparams->roadid);
        }
        // Si se ha acertado una nueva localizacion, se ha modificado el camino,se esta inicializando o se ha resuelto una pregunta o completion.
        if ($geometrysolved || $newroadassigned || $updateroad || $qocsolved->newattempt || $params['initialize']) {
            $lastsuccessfulriddle = get_last_successful_riddle($USER->id, $userparams->groupid, $userparams->roadid, $context);
        }
        // Si se han realizado un nuevo intento de localización o se esta inicializando
        if ($newgeometry || $newroadassigned || $updateroad || $params['initialize']) {
            $userriddles = get_user_progress($userparams->roadid, $userparams->groupid, $USER->id, $treasurehuntid, $context);
        }
        // Si se ha editado el camino aviso.
        if ($updateroad) {
            if (location) {
                $status['msg'] = get_string('errsendiglocation', 'treasurehunt');
                $status['code'] = 1;
            }
            if (answerid) {
                $status['msg'] = get_string('errsendiganswer', 'treasurehunt');
                $status['code'] = 1;
            }
        }
        $result = array();
        $result['infomsg'] = $updates;
        $result['attempttimestamp'] = $newattempttimestamp;
        $result['roadtimestamp'] = $newroadtimestamp;
        $result['status'] = $status;
        $result['riddles'] = $userriddles;
        $result['lastsuccessfulriddle'] = $lastsuccessfulriddle;
        //$result['roadfinished'] = $roadfinished;
        $result['historicalattempts'] = $historicalattempts;
        return $result;
    }

}
