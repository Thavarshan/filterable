<?php

namespace Filterable\Definitions;

enum Feature: string
{
    case Validation = 'validation';
    case Permissions = 'permissions';
    case RateLimit = 'rateLimit';
    case Caching = 'caching';
    case Logging = 'logging';
    case Performance = 'performance';
    case Optimization = 'optimization';
    case MemoryManagement = 'memoryManagement';
    case FilterChaining = 'filterChaining';
    case ValueTransformation = 'valueTransformation';
}
