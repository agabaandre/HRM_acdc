<?php

namespace App\Enums;

enum WorkItemType: string
{
    case Epic = 'epic';
    case Story = 'story';
    case Task = 'task';
    case Subtask = 'subtask';
    case Bug = 'bug';
    case Incident = 'incident';
    case Spike = 'spike';
    case Chore = 'chore';

    public function label(): string
    {
        return match ($this) {
            self::Epic => 'Epic',
            self::Story => 'User story',
            self::Task => 'Task',
            self::Subtask => 'Subtask',
            self::Bug => 'Defect',
            self::Incident => 'Incident',
            self::Spike => 'Spike',
            self::Chore => 'Chore',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
