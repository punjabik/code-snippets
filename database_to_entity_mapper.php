<?php

class databaseToEntityMapper
{
	
    /*
    USAGE
    private array $named_parameters_to_db_columns_mappings = [
        Member::class => [
            'role_id' => 'group_id',
            'full_name' => 'full_name',
            'id' => 'id',
            'svj_id' => 'svj_id',
            'ip_address' => 'ip_address',
            'encrypted_password' => 'password',
            'membership' => [
                Membership::class => [
                    'membership_start' => 'membership_start',
                    'membership_end' => 'membership_end'
                ]
            ]
        ]
    ];

        $class_constructor_parameters = $this->getFunctionNamedParametersWithTypesRecursive(Member::class);

        $method_named_parameters = $this->getMethodNamedParameters(Member::class);

        $instantiated_parameters = instantiateMethodParameters($method_named_parameters, $database_row, $this->named_parameters_to_db_columns_mappings);

        $instantiated_parameters['share'] = (($database_row['share_numerator'] && $database_row['share_denominator']) ? Share::fromNumeratorAndDenominator($database_row['share_numerator'], $database_row['share_denominator']) : NULL);
        $instantiated_parameters['action_send'] = $database_row['action_send'] ? $database_row['action_send']: NULL;

        return Member(...$instantiated_parameters);

    */
	
    protected function getMethodNamedParameters(string $class_name, string $method_name = '__construct'): ?array
    {
        $class = [];

        if (!class_exists($class_name)) {
            return NULL;
        }

        $ref = new \ReflectionMethod($class_name, $method_name);
        $parameters = $ref->getParameters();

        foreach ($parameters as $parameter) {
            $parameter_name = $parameter->getName();
            $parameter_type_name = $parameter->getType()->getName();

            $sub = $this->getMethodNamedParameters($parameter_type_name);

            if ($sub) {
                $class = array_merge($class, $sub);
            }

            if (class_exists($parameter_type_name)) {
                $class[$class_name][$parameter_name] = $parameter_type_name;
            }
        }

        return $class;
    }

    protected function instantiateMethodParameters(array $method_named_parameters, array $data, array $named_parameters_to_db_columns_mappings): array
    {
        $instantiated_parameters = [];

        if (count($named_parameters_to_db_columns_mappings) > 1) {
            throw new RepositoryException(
                'Named parameters to DB columns mappings needs to be defined only for one main class.'
            );
        }

        $class_name = key($named_parameters_to_db_columns_mappings);
        $mapping_for_class = $named_parameters_to_db_columns_mappings[$class_name];
        $class_constructor_named_parameters = $method_named_parameters[$class_name];

        foreach ($mapping_for_class as $parameter_name => $data_key) {
            if (!array_key_exists($parameter_name, $class_constructor_named_parameters)) {
                throw new RepositoryException(
                    'Ve třídě ' . (($class_name) ? $class_name . ' ' : '') . 'nenalezen požadovaný parametr: ' . $parameter_name
                );
            }

            if (is_array($data_key)) {
                $instantiated_parameters[$parameter_name] = new $class_constructor_named_parameters[$parameter_name](...$this->instantiateMethodParameters($method_named_parameters, $data, $data_key));

                continue;
            }

            // Key not found in data. Svaing null
            if (!array_key_exists($data_key, $data) || is_null($data[$data_key])) {
                $instantiated_parameters[$parameter_name] = null;
                continue;
            }

            $instantiated_parameters[$parameter_name] = new $class_constructor_named_parameters[$parameter_name]($data[$data_key]);
        }

        return $instantiated_parameters;
    }
}
