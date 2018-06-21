<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;
use DateTime;
use DateInterval;

class Briefing extends Model
{
    public $timestamps = true;

    protected $table = 'briefing';

    protected $fillable = [
        'code', 'briefing_id',
        'job_id', 'client_id', 'event', 'deadline', 'job_type_id', 'agency_id', 'attendance_id',
        'creation_id', 'rate', 'competition_id', 'last_provider', 'estimated_time', 'not_client',
        'how_come_id', 'approval_expectation_rate', 'main_expectation_id', 'available_date', 'budget',
        'status_id'
    ];

    protected $dates = [
        'created_at', 'updated_at'
    ];

    public static function loadForm() {
        $dateNext  = Briefing::getNextAvailableDate();

        return [
            'jobs' => Job::all(),
            'job_types' => JobType::all(),
            'attendances' => Employee::canInsertClients(),
            'employees' => Employee::all(),
            'creations' => Employee::whereHas('department', function($query) {
                $query->where('description', '=', 'Criação');
            })->get(),
            'creation' => $dateNext['creation'],
            'competitions' => BriefingCompetition::all(),
            'main_expectations' => BriefingMainExpectation::all(),
            'levels' => BriefingLevel::all(),
            'how_comes' => BriefingHowCome::all(),
            'presentations' => BriefingPresentation::all(),
            'status' => BriefingStatus::all(),
            'available_date' => $dateNext['available_date']
        ];
    }

    public static function getNextAvailableDate($dateParam = 'now') {
        $date = new DateTime($dateParam);
        $weekDayDiff = ((int) $date->format('N')) > 5 ? ((int) $date->format('N') - 5) + 1 :  0;
        $date->add(new DateInterval('P' . ($weekDayDiff) . 'D'));
        
        $resultCreation = DB::table('employee')
        ->select('employee.id')
        ->leftJoin('department', 'department.id', '=', 'employee.department_id')
        ->where('department.description','=','Criação')
        ->get();

        $nextCreationId = null;
        $creations = [];

        foreach($resultCreation as $creation) {
            $creations[] = $creation->id;
        }

        $dateNotFound = true;

        do {
            $nextBriefings = Briefing::where('available_date', '>=', $date->format('Y-m-d'))
            ->orderBy('available_date', 'ASC')
            ->orderBy('creation_id', 'ASC')
            ->limit(30)
            ->get();

            if($nextBriefings->count() == 0) {
                $dateNotFound = false;
                $nextCreationId = $creations[0];
                break;
            }

            $i = 0;

            foreach($nextBriefings as $nextBriefing) {
                $available_date = new DateTime($nextBriefing->available_date);

                $reviewDates = $nextBriefings->reject(function ($nextBriefing) use ($available_date, $date) {
                    return (new DateTime($nextBriefing->available_date))->format('d') != $date->format('d');
                });

                if($reviewDates->count() == 0) {
                    foreach($creations as $creation) {
                        $lastBriefingOfCreation = Briefing::where('creation_id', '=', $creation)
                        ->orderBy('available_date', 'DESC')
                        ->limit(1)
                        ->first();

                        $estimatedTimeOfCreation = (int) ceil($lastBriefingOfCreation->estimated_time);

                        /*
                        if((int) $date->format('d') == 1) {
                            dd($lastBriefingOfCreation->estimated_time % 1 == 0, $lastBriefingOfCreation->estimated_time);
                            dd($date, $reviewDates, $availableDateOfCreation, $creation, $lastBriefingOfCreation->available_date, $estimatedTimeOfCreation);
                        }
                        */

                        $availableDateOfCreation = new DateTime($lastBriefingOfCreation->available_date);
                        $availableDateOfCreation->add(new DateInterval('P' . ($estimatedTimeOfCreation) . 'D'));


                        // Verifica se o próximo criação está disponível para aquela data
                        if($availableDateOfCreation <= $date) {
                            $nextCreationId = $creation;
                            $dateNotFound = false;
                            break;
                        } else {
                            $nextCreationId = null;
                        }

                        $i++;
                    }
                    
                    if(!$dateNotFound) {
                        break;
                    }
                } else if($reviewDates->count() < count($creations)) {
                    $tempArray = $reviewDates->map(function($item, $key) {
                        return $item->creation_id;
                    });

                    $freeCreations = array_diff($creations, $tempArray->toArray());
                    foreach($freeCreations as $freeCreation) {
                        $lastBriefingOfCreation = Briefing::where('creation_id', '=', $freeCreation)
                        ->orderBy('available_date', 'DESC')
                        ->limit(1)
                        ->first();

                        /* Nenhum trabalho para o criação, atribuir um */
                        if($lastBriefingOfCreation == null) {
                            $nextCreationId = $freeCreation;
                            $dateNotFound = false;
                            break;
                        }

                        $estimatedTimeOfCreation = (int) ceil($lastBriefingOfCreation->estimated_time);
                        $availableDateOfCreation = new DateTime($lastBriefingOfCreation->available_date);
                        $availableDateOfCreation->add(new DateInterval('P' . ($estimatedTimeOfCreation) . 'D'));

                        // Verifica se o próximo criação está disponível para aquela data
                        if($availableDateOfCreation <= $date) {
                            $nextCreationId = $freeCreation;
                            $dateNotFound = false;
                            break;
                        } else {                        
                            //Retroceder datas para ver se há compromisso no buraco
                            $tempDate = clone $date;
                            $tempDate->sub(new DateInterval('P10D'));
                            $previousBriefing = Briefing::where('creation_id', '=', $freeCreation)
                            ->where('available_date', '>=', $tempDate->format('Y-m-d'))
                            ->where('available_date', '<=', $lastBriefingOfCreation->available_date)
                            ->orderBy('available_date', 'ASC')
                            ->get();    
                            
                            $firstDate = new DateTime($previousBriefing[0]->available_date);
                            $lastDate = new DateTime($previousBriefing[count($previousBriefing) - 1]->available_date);
                            $interval = (int) $lastDate->diff($firstDate)->format('%d');

                            for($index = 1; $index <= $interval; $i++) {
                                $filterArray = $previousBriefing->reject(function ($pBriefing) use ($date) {
                                    return (new DateTime($pBriefing->available_date))->format('d') != $date->format('d');
                                });

                                foreach($filterArray as $fa) {
                                    $estimatedTimeOfCreation = (int) ceil($fa->estimated_time);
                                    $availableDateOfCreation = new DateTime($fa->available_date);
                                    $availableDateOfCreation->add(new DateInterval('P' . ($estimatedTimeOfCreation) . 'D'));
                                    
                                    if($availableDateOfCreation > $tempDate) {
                                        $tempDate = $availableDateOfCreation;

                                        if($availableDateOfCreation > $date) {
                                            break;
                                        }
                                    }
                                }
                                    
                                if($tempDate == $date) {
                                    $nextCreationId = $freeCreation;
                                    $dateNotFound = false;
                                    break;
                                }

                                $tempDate->add(new DateInterval('P1D'));
                            }                           
                        }
                    }
                    
                    if(!$dateNotFound) {
                        break;
                    } else {
                        $nextCreationId = null;
                    }
                }

                $date->add(new DateInterval('P1D'));
                $weekDayDiff = ((int) $date->format('N')) > 5 ? ((int) $date->format('N') - 5) + 1 :  0;
                $date->add(new DateInterval('P' . ($weekDayDiff) . 'D'));
                //$i++;
            }
        } while ($dateNotFound);

        return ['available_date' => $date->format('Y-m-d'), 'creation' => Employee::find($nextCreationId)];
    }

    public static function recalculateNextDate($nextEstimatedTime) {
        $date = new DateTime('now');
        $weekDayDiff = ((int) $date->format('N')) > 5 ? ((int) $date->format('N') - 5) + 1 :  0;
        $date->add(new DateInterval('P' . ($weekDayDiff) . 'D'));
        
        $resultCreation = DB::table('employee')
        ->select('employee.id')
        ->leftJoin('department', 'department.id', '=', 'employee.department_id')
        ->where('department.description','=','Criação')
        ->get();

        $nextCreationId = null;
        $creations = [];

        foreach($resultCreation as $creation) {
            $creations[] = $creation->id;
        }

        $dateNotFound = true;

        do {
            $nextBriefings = Briefing::where('available_date', '>=', $date->format('Y-m-d'))
            ->orderBy('available_date', 'ASC')
            ->orderBy('creation_id', 'ASC')
            ->limit(30)
            ->get();

            if($nextBriefings->count() == 0) {
                $dateNotFound = false;
                $nextCreationId = $creations[0];
                break;
            }

            $i = 0;

            foreach($nextBriefings as $nextBriefing) {
                $available_date = new DateTime($nextBriefing->available_date);

                $reviewDates = $nextBriefings->reject(function ($nextBriefing) use ($available_date, $date) {
                    return (new DateTime($nextBriefing->available_date))->format('d') != $date->format('d');
                });

                if($reviewDates->count() == 0) {
                    foreach($creations as $creation) {
                        $lastBriefingOfCreation = Briefing::where('creation_id', '=', $creation)
                        ->orderBy('available_date', 'DESC')
                        ->limit(1)
                        ->first();
                        
                        if($nextEstimatedTime < 1) {
                            $estimatedTimeOfCreation = (int) ceil($lastBriefingOfCreation->estimated_time + $nextEstimatedTime) - 1;

                            if($estimatedTimeOfCreation < 1) {
                                $estimatedTimeOfCreation = 1;
                            }
                        } else {
                            $estimatedTimeOfCreation = (int) ceil($lastBriefingOfCreation->estimated_time);
                        }

                        $availableDateOfCreation = new DateTime($lastBriefingOfCreation->available_date);
                        $availableDateOfCreation->add(new DateInterval('P' . ($estimatedTimeOfCreation) . 'D'));

                        //if((int) $date->format('d') == 1 && $i == 1) {
                            //dd($date, $reviewDates, $availableDateOfCreation, $creation, $lastBriefingOfCreation->available_date, $estimatedTimeOfCreation);
                        //}

                        // Verifica se o próximo criação está disponível para aquela data
                        if($availableDateOfCreation <= $date) {
                            $nextCreationId = $creation;
                            $dateNotFound = false;
                            break;
                        } else {
                            $nextCreationId = null;
                        }

                        $i++;
                    }
                    
                    if(!$dateNotFound) {
                        break;
                    }
                } else if($reviewDates->count() < count($creations)) {
                    $tempArray = $reviewDates->map(function($item, $key) {
                        return $item->creation_id;
                    });

                    $freeCreations = array_diff($creations, $tempArray->toArray());
                    foreach($freeCreations as $freeCreation) {
                        $lastBriefingOfCreation = Briefing::where('creation_id', '=', $freeCreation)
                        ->orderBy('available_date', 'DESC')
                        ->limit(1)
                        ->first();

                        if($nextEstimatedTime < 1) {
                            $estimatedTimeOfCreation = (int) ceil($lastBriefingOfCreation->estimated_time + $nextEstimatedTime) - 1;

                            if($estimatedTimeOfCreation < 1) {
                                $estimatedTimeOfCreation = 1;
                            }
                        } else {
                            $estimatedTimeOfCreation = (int) ceil($lastBriefingOfCreation->estimated_time);
                        }

                        $availableDateOfCreation = new DateTime($lastBriefingOfCreation->available_date);
                        $availableDateOfCreation->add(new DateInterval('P' . ($estimatedTimeOfCreation) . 'D'));

                        // Verifica se o próximo criação está disponível para aquela data
                        if($availableDateOfCreation <= $date) {
                            $nextCreationId = $freeCreation;
                            $dateNotFound = false;
                            break;
                        } else {
                            $nextCreationId = null;
                        }
                    }
                    
                    if(!$dateNotFound) {
                        break;
                    }
                }

                $date->add(new DateInterval('P1D'));
                $weekDayDiff = ((int) $date->format('N')) > 5 ? ((int) $date->format('N') - 5) + 1 :  0;
                $date->add(new DateInterval('P' . ($weekDayDiff) . 'D'));
                //$i++;
            }
        } while ($dateNotFound);

        return ['available_date' => $date->format('Y-m-d'), 'creation' => Employee::find($nextCreationId)];
    }

    public static function edit(array $data) {
        Briefing::checkData($data, true);

        $id = $data['id'];
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;
        $briefing_id = isset($data['briefing']['id']) ? $data['briefing']['id'] : null;
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $creation_id = isset($data['creation']['id']) ? $data['creation']['id'] : null;
        $briefing = Briefing::find($id);
        $oldBriefing = clone $briefing;
        $briefing->update(
            array_merge($data, [
                'job_id' => $data['job']['id'],
                'client_id' => $client_id,
                'agency_id' => $agency_id,
                'briefing_id' => $briefing_id,
                'responsible_id' => $responsible_id,
                'main_expectation_id' => $data['main_expectation']['id'],
                'status_id' => $data['status']['id'],
                'how_come_id' => $data['how_come']['id'],
                'attendance_id' => $data['attendance']['id'],
                'creation_id' => $creation_id,
                'competition_id' => $data['competition']['id'],
            ])
        );
        //$briefing->editChild($data);

        $arrayPresentations = !isset($data['presentations']) ? [] : $data['presentations'];
        $briefing->savePresentations($arrayPresentations);

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $briefing->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $briefing->editFiles($arrayFiles);

        return $briefing;
    }

    public static function insert(array $data) {
        Briefing::checkData($data);
        $code = Briefing::generateCode();
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;
        $briefing_id = isset($data['briefing']['id']) ? $data['briefing']['id'] : null;
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $creation_id = isset($data['creation']['id']) ? $data['creation']['id'] : null;

        $briefing = new Briefing(
            array_merge($data, [
                'code' => $code,
                'job_id' => $data['job']['id'],
                'client_id' => $client_id,
                'briefing_id' => $briefing_id,
                'responsible_id' => $responsible_id,
                'main_expectation_id' => $data['main_expectation']['id'],
                'status_id' => $data['status']['id'],
                'how_come_id' => $data['how_come']['id'],
                'job_type_id' => $data['job_type']['id'],
                'agency_id' => $agency_id,
                'attendance_id' => $data['attendance']['id'],
                'creation_id' => $creation_id,
                'competition_id' => $data['competition']['id'],
            ])
        );

        $briefing->save();
        //$briefing->saveChild($data);

        $arrayPresentations = !isset($data['presentations']) ? [] : $data['presentations'];
        $briefing->savePresentations($arrayPresentations);

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $briefing->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $briefing->saveFiles($arrayFiles);

        return $briefing;
    }

    public static function downloadFile($id, $type, $file) {
        $content = '';
        $mime = '';
        $briefing = Briefing::find($id);
        $user = User::logged();
        $department = $user->employee->department->description;

        if(!in_array($department, ['Administradores', 'Criação', 'Diretoria', 'Atendimento', 'Produção'])) {
            throw new \Exception('Somente o departamento de criação, atendimento e produção podem visualizar.');
        }

        if(is_null($briefing)) {
            throw new \Exception('O briefing solicitado não existe.');
        }

        switch($type) {
            case 'briefing': {
                $path = resource_path('assets/files/briefings/') . $briefing->id;

                if(!is_file($path . '/' . $file)) {
                    throw new \Exception('O arquivo solicitado não existe.');
                }

                $path .= '/' . $file;
                
                $content = file_get_contents($path);
                $mime = mime_content_type($path);
                break;
            }
            case 'stand': {
                $path = resource_path('assets/files/stands/') . $briefing->stand->id;

                if(!is_file($path . '/' . $file)) {
                    throw new \Exception('O arquivo solicitado não existe.');
                }
                
                $path .= '/' . $briefing->stand->{$file};
                
                $fp = fopen($path, 'r');
                $content = fread($fp, filesize($path));
                $content = addslashes($content);
                fclose($fp);
                $mime = mime_content_type($path);
                break;
            } 
            default: {
                throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
            }
        }

        return $path;
    }

    public static function remove($id) {
        $briefing = Briefing::find($id);
        $oldBriefing = clone $briefing;
        //$briefing->deleteChild();
        $briefing->presentations()->detach();
        $briefing->levels()->detach();
        $briefing->deleteFiles();
        $briefing->delete();
    }

    public static function list() {
        $briefings = Briefing::orderBy('available_date', 'asc')->paginate(20);

        foreach($briefings as $briefing) {
            $briefing->agency;
            $briefing->creation;
            $briefing->job;
            $briefing->job_type;
            $briefing->attendance;
            $briefing->client;
            $briefing->status;
            $briefing->responsible;
            $briefing->briefing;
        }

        return $briefings;
    }

    public static function get(int $id) {
        $briefing = Briefing::find($id);
        $briefing->job;
        $briefing->job_type;
        $briefing->client;
        $briefing->main_expectation;
        $briefing->levels;
        $briefing->how_come;
        $briefing->agency;
        $briefing->attendance;
        $briefing->creation;
        $briefing->competition;
        $briefing->presentations;
        $briefing->files;
        $briefing->status;
        $briefing->responsible;
        $briefing->briefing;

        //Briefing::getBriefingChild($briefing);

        return $briefing;
    }

    public static function filter($params) {
        $iniDate = isset($params['iniDate']) ? $params['iniDate'] : null;
        $finDate = isset($params['finDate']) ? $params['finDate'] : null;
        $status = isset($params['status']) ? $params['status'] : null;
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;
        $briefings = Briefing::select();
        $briefings->orderBy('available_date', 'ASC');
        $briefings->orderBy('attendance_id', 'ASC');

        if($iniDate != null && $finDate != null) {
            $briefings->where('available_date', '>=', $iniDate);
            $briefings->where('available_date', '<=', $finDate);
        }
        
        if($status != null) {
            $briefings->where('status_id', '=', $status);
        }

        if($paginate) {
            $briefings = $briefings->paginate(50);
            
            foreach($briefings as $briefing) {
                $briefing->job;
                $briefing->job_type;
                $briefing->client;
                $briefing->main_expectation;
                $briefing->levels;
                $briefing->how_come;
                $briefing->agency;
                $briefing->attendance;
                $briefing->creation;
                $briefing->competition;
                $briefing->presentations;
                $briefing->files;
                $briefing->status;
                $briefing->responsible;
                $briefing->briefing;
            }
        } else {
            $briefings = $briefings->get();
            
            foreach($briefings as $briefing) {
                $briefing->job;
                $briefing->job_type;
                $briefing->client;
                $briefing->main_expectation;
                $briefing->levels;
                $briefing->how_come;
                $briefing->agency;
                $briefing->attendance;
                $briefing->creation;
                $briefing->competition;
                $briefing->presentations;
                $briefing->files;
                $briefing->status;
                $briefing->responsible;
                $briefing->briefing;
            }

            $briefings = ['data' => $briefings, 'page' => 0, 'total' => $briefings->count()];
        }

        return $briefings;
    }

    public static function editAvailableDate(array $data) {
        $id = $data['id'];
        $briefing = Briefing::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;
        $creation_id = isset($data['creation_id']) ? $data['creation_id'] : null;
        $briefing->update(['available_date' => $available_date, 'creation_id' => $creation_id]);
        return $briefing;
    }

    
    #My Briefing#
    public static function myEditAvailableDate(array $data) {
        $id = $data['id'];
        $briefing = Briefing::find($id);
        $available_date = isset($data['available_date']) ? $data['available_date'] : null;

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse briefing.');
        }

        $briefing->update(['available_date' => $available_date]);
        return $briefing;
    }

    public static function editMyBriefing(array $data) {
        Briefing::checkData($data, true);

        $id = $data['id'];
        $briefing = Briefing::find($id);
        $agency_id = isset($data['agency']['id']) ? $data['agency']['id'] : null;
        $client_id = isset($data['client']['id']) ? $data['client']['id'] : null;
        $briefing_id = isset($data['briefing']['id']) ? $data['briefing']['id'] : null;
        $responsible_id = isset($data['responsible']['id']) ? $data['responsible']['id'] : null;
        $creation_id = isset($data['creation']['id']) ? $data['creation']['id'] : null;

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse briefing.');
        }

        $oldBriefing = clone $briefing;
        $briefing->update(
            array_merge($data, [
                'job_id' => $data['job']['id'],
                'client_id' => $client_id,
                'agency_id' => $agency_id,
                'briefing_id' => $briefing_id,
                'responsible_id' => $responsible_id,
                'main_expectation_id' => $data['main_expectation']['id'],
                'status_id' => $data['status']['id'],
                'how_come_id' => $data['how_come']['id'],
                'attendance_id' => $data['attendance']['id'],
                'creation_id' => $creation_id,
                'competition_id' => $data['competition']['id']
            ])
        );
        //$briefing->editChild($data);        

        $arrayPresentations = !isset($data['presentations']) ? [] : $data['presentations'];
        $briefing->savePresentations($arrayPresentations);

        $arrayLevels = !isset($data['levels']) ? [] : $data['levels'];
        $briefing->saveLevels($arrayLevels);

        $arrayFiles = !isset($data['files']) ? [] : $data['files'];
        $briefing->editFiles($arrayFiles);

        return $briefing;
    }

    public function savePresentations(array $data) {
        $this->presentations()->detach();

        foreach($data as $presentation) {
            $this->presentations()->attach($presentation['id']);
        }
    }

    public function saveLevels(array $data) {
        $this->levels()->detach();
        
        foreach($data as $level) {
            $this->levels()->attach($level['id']);
        }
    }

    public static function downloadFileMyBriefing($id, $type, $file) {
        $content = '';
        $mime = '';
        $briefing = Briefing::find($id);
        $user = User::logged();

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para fazer downloads desse briefing.');
        }

        if(is_null($briefing)) {
            throw new \Exception('O briefing solicitado não existe.');
        }

        switch($type) {
            case 'briefing': {
                $path = resource_path('assets/files/briefings/') . $briefing->id;

                if(!is_file($path . '/' . $file)) {
                    throw new \Exception('O arquivo solicitado não existe.');
                }

                $path .= '/' . $file;
                
                $content = file_get_contents($path);
                $mime = mime_content_type($path);
                break;
            }
            case 'stand': {
                $path = resource_path('assets/files/stands/') . $briefing->stand->id;

                if(!is_file($path . '/' . $file)) {
                    throw new \Exception('O arquivo solicitado não existe.');
                }
                
                $path .= '/' . $briefing->stand->{$file};
                
                $fp = fopen($path, 'r');
                $content = fread($fp, filesize($path));
                $content = addslashes($content);
                fclose($fp);
                $mime = mime_content_type($path);
                break;
            } 
            default: {
                throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
            }
        }

        return $path;
    }

    public static function removeMyBriefing($id) {
        $briefing = Briefing::find($id);

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse briefing.');
        }

        $oldBriefing = clone $briefing;
        //$briefing->deleteChild();
        $briefing->presentations()->detach();
        $briefing->levels()->detach();
        $briefing->deleteFiles();
        $briefing->delete();
    }

    public static function listMyBriefing() {
        $briefings = Briefing::orderBy('available_date', 'asc')
         ->where('attendance_id', '=', User::logged()->employee->id)
         ->paginate(20);

        foreach($briefings as $briefing) {
            $briefing->agency;
            $briefing->creation;
            $briefing->job;
            $briefing->job_type;
            $briefing->attendance;
            $briefing->client;
            $briefing->status;
            $briefing->responsible;
            $briefing->briefing;
        }

        return $briefings;
    }

    public static function getMyBriefing(int $id) {
        $briefing = Briefing::find($id);

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para visualizar esse briefing.');
        }

        $briefing->job;
        $briefing->job_type;
        $briefing->client;
        $briefing->main_expectation;
        $briefing->levels;
        $briefing->how_come;
        $briefing->agency;
        $briefing->attendance;
        $briefing->status;
        $briefing->creation;
        $briefing->competition;
        $briefing->presentations;
        $briefing->files;
        $briefing->responsible;
        $briefing->briefing;

        //Briefing::getBriefingChild($briefing);
        return $briefing;
    }

    public static function filterMyBriefing($query) {
       $briefings = Briefing::where('event', 'like', $query . '%')
        ->where('attendance_id', '=', User::logged()->employee->id)
        ->paginate(50);

        foreach($briefings as $briefing) {
            $briefing->job;
            $briefing->job_type;
            $briefing->client;
            $briefing->main_expectation;
            $briefing->levels;
            $briefing->how_come;
            $briefing->agency;
            $briefing->attendance;
            $briefing->creation;
            $briefing->competition;
            $briefing->presentations;
            $briefing->files;
            $briefing->status;
            $briefing->responsible;
            $briefing->briefing;
        }

        return $briefings;
    }

    public static function generateCode() {
        $result = DB::table('briefing')
        ->select(DB::raw('(MAX(code) + 1) as code'))
        ->where(DB::raw('YEAR(CURRENT_DATE())'), '=', DB::raw('YEAR(created_at)'))
        ->first();

        if($result->code == null) {
            return 1;
        }

        return $result->code; 
    }

    /*
    public function saveFiles($data) {
        $path = resource_path('assets/files/briefings/') . $this->id;
        $files = Briefing::fileArrayFields();

        foreach($files as $file => $field) {
            if(!isset($data[$file])) {
                throw new \Exception('O arquivo ' . $field . ' não foi informado.');
            }
        }

        mkdir($path);

        foreach($files as $file => $field) {
            rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
        }
    }
    */

    public function saveFiles(array $data) {
        $path = resource_path('assets/files/briefings/') . $this->id;

        if(!is_dir($path)) {
            mkdir($path);
        }

        foreach($data as $file) {
            rename(sys_get_temp_dir() . '/' .  $file['name'], $path . '/' . $file['name']);
            $this->files()->save(new BriefingFile([
                'briefing_id' => $this->id,
                'filename' => $file['name']
            ]));
        }
    }

    public function editFiles(array $data) {
        $browserFiles = [];
        $path = resource_path('assets/files/briefings/') . $this->id;

        if(!is_dir($path)) {
            mkdir($path);
        }

        foreach($data as $file) {
            $browserFiles[] = $file['name'];
            $oldFile = $this->files()
            ->where('briefing_file.filename', '=', $file['name'])
            ->first();

            if(is_file(sys_get_temp_dir() . '/' .  $file['name'])) {
                // Substituir / criar arquivo em caso de não existir
                rename(sys_get_temp_dir() . '/' .  $file['name'], $path . '/' . $file['name']);
                
                if(is_null($oldFile)) {
                    $this->files()->save(new BriefingFile([
                        'briefing_id' => $this->id,
                        'filename' => $file['name']
                    ]));
                }
            }
        }

        foreach($this->files as $file) {
            try {
                if(!in_array($file->filename, $browserFiles)) {
                    unlink($path . '/' . $file->filename);
                    $file->delete();
                }
            } catch(\Exception $e) {}
        }
    }

    public function deleteFiles() {
        $path = resource_path('assets/files/briefings/') . $this->id;
        foreach($this->files as $file) {
            try {
                unlink($path . '/' . $file->filename);
                $file->delete();
            } catch(\Exception $e) {}
        } 
    }

    /*
    public function editFiles(Briefing $oldBriefing, $data) {
        $updatedFiles = [];
        $path = resource_path('assets/files/briefings/') . $this->id;
        $files = Briefing::fileArrayFields();

        foreach($files as $file => $field) {    
            if($oldBriefing->{$file} != $data[$file]) {
                $updatedFiles[] = $file;
            }   
        }

        try {
            foreach($updatedFiles as $file) {
                unlink($path . '/' . $oldBriefing->{$file});
                rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
            }
        } catch(\Exception $e) {}
    }
    */
 
    public function saveChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::insert($this, $data['stand']);
        }
    }
 
    public function saveFilesChild($data) {
        if($this->job_type->description === 'Stand') {
            $this->stand->saveFiles($data['stand']);
        }
    }
 
    public function editChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::edit($data['stand']);
        }
    }
 
    public function editFilesChild($oldChild, $data) {
        if($this->job_type->description === 'Stand') {
            $this->stand->editFiles($oldChild, $data['stand']);
        }
    }
 
    public function deleteChild() {
        if($this->job_type->description === 'Stand') {
            Stand::remove($this->stand->id);
        }
    }
 
    public static function getBriefingChild(Briefing $briefing) {
        if($briefing->job_type->description === 'Stand') {
            $stand = $briefing->stand;
            $stand->briefing;
            $stand->configuration;
            $stand->genre;
            $stand->column = $stand->column == 0 ? ['id' => 0] : ['id' => 1];
            $items = $stand->items;
            foreach($items as $item) {
                $item->type;
            }

            return $stand;
        }
    }

    public static function checkData(array $data, $editMode = false) {
        if(!isset($data['job']['id'])) {
            throw new \Exception('Job do briefing não informado!');
        }

        if(!isset($data['status']['id'])) {
            throw new \Exception('Status não informado!');
        }

        if(!isset($data['main_expectation']['id'])) {
            throw new \Exception('Expectativa principal do briefing não informada!');
        }

        if(!isset($data['how_come']['id'])) {
            throw new \Exception('Motivo do briefing não informado!');
        }

        if(!isset($data['job_type']['id']) && !$editMode) {
            throw new \Exception('Tipo de job do briefing não informado!');
        }

        /*
        if(!isset($data['agency']['id'])) {
            throw new \Exception('Agência do briefing não cadastrada!');
        }
        */

        if(!isset($data['attendance']['id'])) {
            throw new \Exception('Atendimento do briefing não informado!');
        }

        /*
        if(!isset($data['creation']['id'])) {
            throw new \Exception('Criação do briefing não informada!');
        }
        */

        if(!isset($data['competition']['id'])) {
            throw new \Exception('Concorrência do briefing não informada!');
        }
    }

    public function stand() {
        return $this->hasOne('App\Stand', 'briefing_id');
    }

    public function job() {
        return $this->belongsTo('App\Job', 'job_id');
    }

    public function client() {
        return $this->belongsTo('App\Client', 'client_id');
    }

    public function job_type() {
        return $this->belongsTo('App\JobType', 'job_type_id');
    }

    public function main_expectation() {
        return $this->belongsTo('App\BriefingMainExpectation', 'main_expectation_id');
    }

    public function level() {
        return $this->belongsTo('App\BriefingLevel', 'level_id');
    }

    public function how_come() {
        return $this->belongsTo('App\BriefingHowCome', 'how_come_id');
    }

    public function agency() {
        return $this->belongsTo('App\Client', 'agency_id');
    }

    public function briefing() {
        return $this->belongsTo('App\Briefing', 'briefing_id');
    }

    public function responsible() {
        return $this->belongsTo('App\Employee', 'responsible_id');
    }

    public function attendance() {
        return $this->belongsTo('App\Employee', 'attendance_id');
    }

    public function creation() {
        return $this->belongsTo('App\Employee', 'creation_id');
    }

    public function competition() {
        return $this->belongsTo('App\BriefingCompetition', 'competition_id');
    }

    public function status() {
        return $this->belongsTo('App\BriefingStatus', 'status_id');
    }

    public function presentations() {
        return $this->belongsToMany('App\BriefingPresentation', 'briefing_presentation_briefing', 'briefing_id', 'presentation_id');
    }

    public function levels() {
        return $this->belongsToMany('App\BriefingLevel', 'briefing_level_briefing', 'briefing_id', 'level_id');
    }

    public function files() {
        return $this->hasMany('App\BriefingFile', 'briefing_id');
    }

    public function setBudgetAttribute($value) {
        $this->attributes['budget'] = (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    public function setEstimatedTimeAttribute($value) {
        $this->attributes['estimated_time'] = (float) str_replace(',', '.', $value);
    }

    public function setEstimated_timeAttribute($value) {
        $this->attributes['estimated_time'] = (float) str_replace(',', '.', $value);
    }
}
