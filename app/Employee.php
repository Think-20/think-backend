<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use DateTime;
use DB;
use Exception;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class Employee extends Model implements NotifierInterface
{
    use SoftDeletes;

    protected $table = 'employee';

    protected $fillable = [
        'name', 'payment', 'position_id', 'department_id', 'schedule_active'
    ];

    protected $hidden = [
        'payment'
    ];

    protected $dates = [
        'deleted_at', 'created_at', 'updated_at'
    ];

    public function getOficialId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getLogo(): string {
        return '/assets/images/users/' . $this->user;
    }

    public function getImageNameGeneration() {
        return $this->image . '_id_' . $this->id;
    }

    public function moveFile() {
        if($this->image == 'sem-foto.jpg' || $this->image == 'users/sem-foto.jpg') return;

        $browserFiles = [];
        $path = public_path('assets/images/users/');

        if(!is_dir($path)) {
            mkdir($path);
        }

        if(is_file(sys_get_temp_dir() . '/' .  $this->image)) {
            rename(sys_get_temp_dir() . '/' .  $this->image, $path . '/' . $this->getImageNameGeneration());
            $this->image = 'users/' . $this->getImageNameGeneration();
            $this->save();
        }
    }    

    public function removeFile() {
        if($this->image == 'sem-foto.jpg' || $this->image == 'users/sem-foto.jpg') return;

        $path = public_path('assets/images/');
        $file = $path . $this->image;

        if(is_file($file)) {
            unlink($file);
        }
    }    

    public static function list() {
        $deleted = isset($data['deleted']) ? $data['deleted'] : null;

        $query = Employee::with('user', 'position', 'department')
        ->orderByRaw('employee.deleted_at IS NOT NULL ASC')
        ->orderBy('name', 'asc');

        if($deleted) {
            $query->withTrashed();
        }

        $employees = $query->paginate(20);

        return [
            'pagination' => $employees,
            'updatedInfo' => Employee::updatedInfo()
        ];
    }

    public static function get(int $id) {
        $employee = Employee::with([
            'user', 'user.functionalities', 'user.displays', 'position', 'department', 'updatedBy'
        ])
        ->where('employee.id', '=', $id)
        ->withTrashed()
        ->first();
                
        if(is_null($employee)) {
            return null;
        }

        $employee->makeVisible('payment');
        $employee->load(['funds', 'cedenteRoles']);
        $role = $employee->cedenteRole();
        $employee->cedente_role = $role ? $role->toApiArray() : null;
        $employee->all_funds = $employee->funds->isEmpty();

        return $employee;
    }

    public static function myGet(int $id) {
        $employee = Employee::with('user', 'position', 'department', 'updatedBy')
        ->where('employee.id', '=', $id)
        ->withTrashed()
        ->first();

        $employee->checkUser(); 
                
        if(is_null($employee)) {
            return null;
        }

        $employee->makeVisible('payment');
        return $employee;
    }

    public static function canInsertClients(array $data = []) {
        $deleted = isset($data['deleted']) && $data['deleted'] === 'true' ? true : false;
        $insertClients = Functionality::where('description', '=', 'Cadastrar um cliente')->first();
        $insertMyClients = Functionality::where('description', '=', 'Cadastrar um cliente (atendimento)')->first();

        $employees = Employee::select('employee.id', 'employee.name', 'employee.position_id', 'employee.department_id')
        ->join('user', 'user.employee_id', '=', 'employee.id')
        ->join('user_functionality', 'user_functionality.user_id', '=', 'user.id')
        ->where('user_functionality.functionality_id', '=', $insertClients->id)
        ->orWhere('user_functionality.functionality_id', '=', $insertMyClients->id)
        ->orderBy('name', 'asc')
        ->distinct();

        if($deleted) {
            $employees->withTrashed();
        }

        return $employees->get();
    }

    public static function filter(array $data) {
        $search = isset($data['search']) ? $data['search'] : null;
        $deleted = isset($data['deleted']) ? $data['deleted'] : null;
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $departmentId = isset($data['department']['id']) ? $data['department']['id'] : null;
        $positionId = isset($data['position']['id']) ? $data['position']['id'] : null;
        $query = Employee::with('user', 'position', 'department');

        if( ! is_null($search) ) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        if( ! is_null($departmentId) ) {
            $query->where('department_id', '=', $departmentId);
        }

        if( ! is_null($positionId) ) {
            $query->where('position_id', '=', $positionId);
        }

        $query->orderByRaw('employee.deleted_at IS NOT NULL ASC')
            ->orderBy('name', 'asc');

        if($deleted) {
            $query->withTrashed();
        }

        if($paginate) {
            $employees = $query->paginate(20);
        } else {
            $employees = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $employees,
            'updatedInfo' => Employee::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $image = isset($data['image']) ? $data['image'] : null;
            $employee = Employee::withTrashed()->find($id);
            $employee->makeVisible('payment');
            $employee->image = isset($data['image']) ? $data['image'] : 'sem-foto.jpg';
            $employee->department_id = isset($data['department']['id']) ? $data['department']['id'] : null;
            $employee->position_id = isset($data['position']['id']) ? $data['position']['id'] : null;
            
            if($employee->image != $employee->getImageNameGeneration()) {
                $employee->moveFile();
            }
            
            $employee->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function myEdit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $image = isset($data['image']) ? $data['image'] : null;
            $name = isset($data['name']) ? $data['name'] : null;

            $employee = Employee::withTrashed()->find($id);
            $employee->checkUser();            
            $employee->image = isset($data['image']) ? $data['image'] : 'sem-foto.jpg';
            
            if($employee->image != $employee->getImageNameGeneration()) {
                $employee->moveFile();
            }
            
            $employee->update([
                'name' => $name
            ]);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function checkUser() {
        if($this->user->id != User::logged()->id) {
            throw new \Exception('Desculpe, você não pode ler ou editar informações de outro usuário.');
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $email = self::generateEmail($data['name']);
            $user = User::where('email', $email)->first();
            if($user){
                throw new Exception('Já existe um usuário com esse nome.');
            }
            $employee = new Employee($data);
            $employee->department_id = isset($data['department']['id']) ? $data['department']['id'] : null;
            $employee->position_id = isset($data['position']['id']) ? $data['position']['id'] : null;
            $employee->updated_by = User::logged()->employee->id;
            $employee->image = isset($data['image']) ? $data['image'] : 'sem-foto.jpg';
            $employee->save();
            $employee->moveFile();
            DB::commit();
            
            self::createUser($data, $employee->id);
            
            return $employee;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Cadastro de employee do modulo de cedentes a partir do zero:
     * name, email e senha enviados pelo front (sem gerar @think / @carmel),
     * user, cedente_role por id e fundos.
     *
     * @param array $data
     * @return Employee
     */
    public static function insertForCedente(array $data)
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        if ($name === '') {
            throw new InvalidArgumentException('name e obrigatorio');
        }

        $roleId = self::parseCedenteRoleId($data, true);
        $email = self::parseCedenteEmail($data, true);
        $password = self::parseCedentePassword($data, true);
        self::assertCedenteEmailAvailable($email);

        list($allFunds, $fundIds) = self::parseCedenteFundsPayload($data);

        DB::beginTransaction();

        try {
            $employee = new Employee();
            $employee->name = $name;
            self::applyCedenteEmployeeDefaults($employee, $data);
            $employee->save();
            $employee->moveFile();

            self::createCedenteUser($employee->id, $email, $password);

            CedenteRole::assignToEmployeeById($employee->id, $roleId);
            self::syncCedenteFunds($employee, $fundIds, $allFunds);

            DB::commit();

            return $employee->fresh(['user', 'funds', 'cedenteRoles', 'department', 'position']);
        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Altera employee do modulo de cedentes: nome, email, senha, funcao e/ou fundos.
     *
     * @param array $data
     * @return Employee
     */
    public static function editForCedente(array $data)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($id < 1) {
            throw new InvalidArgumentException('id e obrigatorio');
        }

        $employee = Employee::withTrashed()->find($id);
        if (!$employee) {
            throw new InvalidArgumentException('Employee nao encontrado');
        }

        $roleId = self::parseCedenteRoleId($data, false);
        $email = self::parseCedenteEmail($data, false);
        $password = self::parseCedentePassword($data, false);
        $updateFunds = array_key_exists('all_funds', $data)
            || array_key_exists('fund_ids', $data)
            || array_key_exists('fundos', $data);

        $allFunds = false;
        $fundIds = [];
        if ($updateFunds) {
            list($allFunds, $fundIds) = self::parseCedenteFundsPayload($data);
        }

        if ($email !== null) {
            self::assertCedenteEmailAvailable($email, $employee->id);
        }

        DB::beginTransaction();

        try {
            if (isset($data['name']) && trim((string) $data['name']) !== '') {
                $employee->name = trim((string) $data['name']);
            }
            if (array_key_exists('department', $data)) {
                $employee->department_id = isset($data['department']['id']) && (int) $data['department']['id'] > 0
                    ? (int) $data['department']['id']
                    : self::resolveCedenteDepartmentId($data);
            }
            if (array_key_exists('position', $data)) {
                $employee->position_id = isset($data['position']['id']) && (int) $data['position']['id'] > 0
                    ? (int) $data['position']['id']
                    : self::resolveCedentePositionId($data);
            }
            if (isset($data['image'])) {
                $employee->image = $data['image'];
                if ($employee->image != $employee->getImageNameGeneration()) {
                    $employee->moveFile();
                }
            }
            $employee->updated_by = User::logged()->employee->id;
            $employee->save();

            $user = $employee->user;
            if (!$user) {
                if ($email === null || $password === null) {
                    throw new InvalidArgumentException('Este employee nao tem usuario. Envie email e password para criar');
                }
                self::createCedenteUser($employee->id, $email, $password);
            } else {
                if ($email !== null) {
                    $user->email = $email;
                }
                if ($password !== null) {
                    $user->password = Hash::make($password);
                }
                $user->save();
            }

            if ($roleId !== null) {
                CedenteRole::assignToEmployeeById($employee->id, $roleId);
            }
            if ($updateFunds) {
                self::syncCedenteFunds($employee, $fundIds, $allFunds);
            }

            DB::commit();

            return $employee->fresh(['user', 'funds', 'cedenteRoles', 'department', 'position']);
        } catch (InvalidArgumentException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param array $data
     * @param bool $required
     * @return int|null
     */
    public static function parseCedenteRoleId(array $data, $required)
    {
        $roleId = null;
        if (isset($data['cedente_role_id'])) {
            $roleId = (int) $data['cedente_role_id'];
        } elseif (isset($data['cedente_role']['id'])) {
            $roleId = (int) $data['cedente_role']['id'];
        }

        if ($roleId === null || $roleId < 1) {
            if ($required) {
                throw new InvalidArgumentException('cedente_role_id e obrigatorio');
            }

            return null;
        }

        return $roleId;
    }

    /**
     * Email informado pelo front. Nao gera dominio @think / @carmel.
     *
     * @param array $data
     * @param bool $required
     * @return string|null
     */
    public static function parseCedenteEmail(array $data, $required)
    {
        if (!array_key_exists('email', $data) || $data['email'] === null || $data['email'] === '') {
            if ($required) {
                throw new InvalidArgumentException('email e obrigatorio');
            }

            return null;
        }

        $email = strtolower(trim((string) $data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('email invalido');
        }

        return $email;
    }

    /**
     * @param array $data
     * @param bool $required
     * @return string|null
     */
    public static function parseCedentePassword(array $data, $required)
    {
        if (!array_key_exists('password', $data) && !array_key_exists('senha', $data)) {
            if ($required) {
                throw new InvalidArgumentException('password e obrigatorio');
            }

            return null;
        }

        $raw = array_key_exists('password', $data) ? $data['password'] : $data['senha'];
        $password = is_string($raw) ? $raw : (string) $raw;
        if (trim($password) === '') {
            if ($required) {
                throw new InvalidArgumentException('password e obrigatorio');
            }

            return null;
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('password deve ter no minimo 6 caracteres');
        }

        return $password;
    }

    /**
     * department_id obrigatorio na tabela employee. Para usuarios so do modulo cedente,
     * usa valor do front se enviado; senao CEDENTE_EMPLOYEE_DEPARTMENT_ID ou um departamento
     * sem privilegios especiais do jobs (evita 1=diretoria, 4=atendimento).
     *
     * @param array $data
     * @return int
     */
    public static function resolveCedenteDepartmentId(array $data = [])
    {
        if (isset($data['department']['id']) && (int) $data['department']['id'] > 0) {
            $id = (int) $data['department']['id'];
            if (Department::find($id)) {
                return $id;
            }
            throw new InvalidArgumentException('department informado nao existe');
        }

        if (isset($data['department_id']) && (int) $data['department_id'] > 0) {
            $id = (int) $data['department_id'];
            if (Department::find($id)) {
                return $id;
            }
            throw new InvalidArgumentException('department_id informado nao existe');
        }

        $configured = env('CEDENTE_EMPLOYEE_DEPARTMENT_ID');
        if ($configured !== null && $configured !== '') {
            $id = (int) $configured;
            if ($id > 0 && Department::find($id)) {
                return $id;
            }
        }

        $cedenteDept = Department::where('description', 'like', '%cedente%')
            ->orderBy('id', 'asc')
            ->first();
        if ($cedenteDept) {
            return (int) $cedenteDept->id;
        }

        // 1 = diretoria (dashboard jobs); 4 = atendimento (notificacoes de clientes)
        $safe = Department::whereNotIn('id', [1, 4])->orderBy('id', 'asc')->first();
        if ($safe) {
            return (int) $safe->id;
        }

        $any = Department::orderBy('id', 'asc')->first();
        if ($any) {
            return (int) $any->id;
        }

        throw new InvalidArgumentException(
            'Nenhum departamento cadastrado. Defina CEDENTE_EMPLOYEE_DEPARTMENT_ID no .env'
        );
    }

    /**
     * position_id obrigatorio na tabela employee. Cargo generico para modulo cedente
     * (evita combos especiais do jobs, ex.: dept 5 + position 7).
     *
     * @param array $data
     * @return int
     */
    public static function resolveCedentePositionId(array $data = [])
    {
        if (isset($data['position']['id']) && (int) $data['position']['id'] > 0) {
            $id = (int) $data['position']['id'];
            if (Position::find($id)) {
                return $id;
            }
            throw new InvalidArgumentException('position informado nao existe');
        }

        if (isset($data['position_id']) && (int) $data['position_id'] > 0) {
            $id = (int) $data['position_id'];
            if (Position::find($id)) {
                return $id;
            }
            throw new InvalidArgumentException('position_id informado nao existe');
        }

        $configured = env('CEDENTE_EMPLOYEE_POSITION_ID');
        if ($configured !== null && $configured !== '') {
            $id = (int) $configured;
            if ($id > 0 && Position::find($id)) {
                return $id;
            }
        }

        $cedentePos = Position::where('description', 'like', '%cedente%')
            ->orWhere('name', 'like', '%cedente%')
            ->orderBy('id', 'asc')
            ->first();
        if ($cedentePos) {
            return (int) $cedentePos->id;
        }

        // 6 e 7 tem regras especiais no cadastro de jobs
        $safe = Position::whereNotIn('id', [6, 7])->orderBy('id', 'asc')->first();
        if ($safe) {
            return (int) $safe->id;
        }

        $any = Position::orderBy('id', 'asc')->first();
        if ($any) {
            return (int) $any->id;
        }

        throw new InvalidArgumentException(
            'Nenhum cargo cadastrado. Defina CEDENTE_EMPLOYEE_POSITION_ID no .env'
        );
    }

    /**
     * payment NOT NULL na tabela employee. Default 10 para modulo cedente.
     *
     * @param array $data
     * @return float
     */
    public static function resolveCedentePayment(array $data = [])
    {
        if (array_key_exists('payment', $data) && $data['payment'] !== null && $data['payment'] !== '') {
            return (float) $data['payment'];
        }

        return (float) env('CEDENTE_EMPLOYEE_PAYMENT', 10);
    }

    /**
     * schedule_active — 0 evita listar o usuario em pickers de tarefas do jobs.
     *
     * @param array $data
     * @return int 0|1
     */
    public static function resolveCedenteScheduleActive(array $data = [])
    {
        if (array_key_exists('schedule_active', $data)) {
            $v = $data['schedule_active'];

            return ($v === true || $v === 1 || $v === '1' || $v === 'true') ? 1 : 0;
        }

        $configured = env('CEDENTE_EMPLOYEE_SCHEDULE_ACTIVE', 0);
        if (is_string($configured)) {
            return in_array(strtolower($configured), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        }

        return (int) $configured === 1 ? 1 : 0;
    }

    /**
     * Campos obrigatorios da tabela employee preenchidos com valores seguros para o modulo cedente.
     *
     * @param Employee $employee
     * @param array $data
     */
    public static function applyCedenteEmployeeDefaults(Employee $employee, array $data = [])
    {
        $employee->department_id = self::resolveCedenteDepartmentId($data);
        $employee->position_id = self::resolveCedentePositionId($data);
        $employee->payment = self::resolveCedentePayment($data);
        $employee->schedule_active = self::resolveCedenteScheduleActive($data);
        $employee->image = (isset($data['image']) && trim((string) $data['image']) !== '')
            ? $data['image']
            : 'sem-foto.jpg';
        $employee->updated_by = User::logged()->employee->id;
    }

    /**
     * @param string $email
     * @param int|null $exceptEmployeeId
     */
    public static function assertCedenteEmailAvailable($email, $exceptEmployeeId = null)
    {
        $query = User::where('email', $email);
        if ($exceptEmployeeId !== null) {
            $query->where('employee_id', '!=', (int) $exceptEmployeeId);
        }
        if ($query->first()) {
            throw new InvalidArgumentException('Ja existe um usuario com esse email');
        }
    }

    /**
     * @param int $employeeId
     * @param string $email
     * @param string $password
     * @return User
     */
    public static function createCedenteUser($employeeId, $email, $password)
    {
        $user = new User();
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->employee_id = $employeeId;
        $user->save();

        return $user;
    }

    /**
     * @param array $data
     * @return array{0: bool, 1: int[]}
     */
    public static function parseCedenteFundsPayload(array $data)
    {
        $allFunds = false;
        if (array_key_exists('all_funds', $data)) {
            $v = $data['all_funds'];
            $allFunds = $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'todos';
        }

        $rawFunds = null;
        if (array_key_exists('fund_ids', $data)) {
            $rawFunds = $data['fund_ids'];
        } elseif (array_key_exists('fundos', $data)) {
            $rawFunds = $data['fundos'];
        }

        if (is_string($rawFunds) && strtolower(trim($rawFunds)) === 'todos') {
            $allFunds = true;
            $rawFunds = null;
        }

        if ($allFunds) {
            return [true, []];
        }

        if ($rawFunds === null) {
            return [true, []];
        }

        if (!is_array($rawFunds)) {
            throw new InvalidArgumentException('fund_ids deve ser um array de ids ou all_funds true');
        }

        if (count($rawFunds) === 0) {
            return [true, []];
        }

        $ids = [];
        foreach ($rawFunds as $item) {
            if (is_array($item) && isset($item['id'])) {
                $ids[] = (int) $item['id'];
            } else {
                $ids[] = (int) $item;
            }
        }
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));

        if (empty($ids)) {
            throw new InvalidArgumentException('fund_ids invalido');
        }

        return [false, $ids];
    }

    /**
     * @param Employee $employee
     * @param int[] $fundIds
     * @param bool $allFunds
     */
    public static function syncCedenteFunds(Employee $employee, array $fundIds, $allFunds)
    {
        if ($allFunds) {
            $employee->funds()->sync([]);

            return;
        }

        $found = Fund::whereIn('id', $fundIds)->pluck('id')->all();
        $found = array_map('intval', $found);
        $missing = array_diff($fundIds, $found);
        if (!empty($missing)) {
            throw new InvalidArgumentException('Fundo nao encontrado: ' . implode(', ', $missing));
        }

        $employee->funds()->sync($fundIds);
    }

    /**
     * Payload de employee no modulo de cedentes (user, papel e fundos).
     *
     * @return array
     */
    public function toCedenteModuleArray()
    {
        $this->load(['user', 'funds', 'cedenteRoles', 'department', 'position']);
        $role = $this->cedenteRole();
        $funds = $this->funds;
        $allFunds = $funds->isEmpty();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'email' => $this->user->email,
            ] : null,
            'cedente_role' => $role ? $role->toApiArray() : null,
            'funds' => $allFunds ? [] : $funds->map(function ($fund) {
                return [
                    'id' => (int) $fund->id,
                    'name' => $fund->name,
                    'code' => $fund->code,
                    'type' => $fund->type,
                ];
            })->values()->all(),
            'all_funds' => $allFunds,
        ];
    }

    public static function createUser($data, $employeeId) {

        // Verifica se o nome existe
        if (!isset($data['name']) || !$employeeId) {
            return null;
        }
        $email = self::generateEmail($data['name']);
        $user = new User();
        $user->email = $email;
        $user->password = Hash::make($email);
        $user->employee_id = $employeeId;
        $user->save();
    }

    public static function generateEmail($name){
        // Divide o nome em palavras
        $words = explode(' ', $name);
    
        // Converte todas as palavras para minúsculas e as une com um ponto
        $email = implode('.', array_map('strtolower', $words));
    
        // Adiciona o domínio do email
        $email .= '@thinkideias.com.br';

        return $email;
    }

    public static function toggleDeleted($id) {
        DB::beginTransaction();
        
        try {
            $employee = Employee::withTrashed()->find($id);

            if($employee->trashed()) {
                $employee->restore();
            } else {
                $employee->delete();
            }
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /*
    public static function remove($id) {
        DB::beginTransaction();
        throw new \Exception('Essa função está temporariamente desabilitada.');
        
        try {
            $employee = Employee::find($id);
            $employee->user->notifications()->delete();
            $employee->user->scheduleBlocks()->delete();
            $employee->user->notificationRules()->delete();
            $employee->user->functionalities()->detach();
            $employee->user->displays()->detach();
            $employee->user->delete();
            $employee->delete();
            $employee->removeFile();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }
    */

    

    public function setNameAttribute($value) {
        $this->attributes['name'] = ucwords(mb_strtolower($value));
    }

    public static function updatedInfo() {
        $lastData = Employee::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => $lastData->updatedBy->name
        ];
    }

    public function position() {
        return $this->belongsTo('App\Position', 'position_id');
    }

    public function user() {
        return $this->hasOne('App\User', 'employee_id');
    }

    public function updatedBy() {
        return $this->belongsTo('App\Employee', 'updated_by')->withTrashed();
    }

    public function department() {
        return $this->belongsTo('App\Department', 'department_id');
    }

    public function funds() {
        return $this->belongsToMany(Fund::class, 'fund_employee', 'employee_id', 'fund_id')
            ->withTimestamps();
    }

    public function cedenteRoles() {
        return $this->belongsToMany(CedenteRole::class, 'cedente_role_employee', 'employee_id', 'cedente_role_id')
            ->withTimestamps();
    }

    /**
     * Papel unico de cedente do employee (preenchimento|avalista|administrador).
     *
     * @return CedenteRole|null
     */
    public function cedenteRole()
    {
        return CedenteRole::forEmployee($this->id);
    }
    
    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
