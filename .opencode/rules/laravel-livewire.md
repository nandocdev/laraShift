# Estándares Críticos de PHP y Laravel

- Fuerza `declare(strict_types=1);` en la primera línea de todo archivo PHP.
- Utiliza tipado estricto y nativo en argumentos y retornos de métodos.
- Previene sistemáticamente el problema N+1 en Eloquent mediante Eager Loading.
- En componentes Livewire, minimiza el almacenamiento de estado público en el servidor para reducir la latencia de hidratación en la red.
- Prohíbe el uso de helpers globales cuando existan abstracciones orientadas a objetos o inyección de dependencias.
