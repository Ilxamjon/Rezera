sealed class AppFailure implements Exception {
  const AppFailure(this.message, {this.code, this.details});

  final String message;
  final String? code;
  final Map<String, dynamic>? details;

  @override
  String toString() => message;
}

class NetworkFailure extends AppFailure {
  const NetworkFailure([super.message = 'error_network']);
}

class UnauthorizedFailure extends AppFailure {
  const UnauthorizedFailure([super.message = 'error_unauthorized']);
}

class ValidationFailure extends AppFailure {
  const ValidationFailure(super.message, {super.details})
      : super(code: 'validation');
}

class ConflictFailure extends AppFailure {
  const ConflictFailure([
    super.message = 'error_conflict',
    String? code,
  ]) : super(code: code ?? 'conflict');
}

class UnknownFailure extends AppFailure {
  const UnknownFailure([
    super.message = 'error_unknown',
    String? code,
  ]) : super(code: code);
}
